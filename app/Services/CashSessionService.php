<?php

namespace App\Services;

use App\Enums\CashSessionStatus;
use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use App\Models\CashDenomination;
use App\Models\CashSession;
use App\Models\Expense;
use App\Models\Payable;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Cierre de caja diario (arqueo). Un cierre por `businessDate`.
 *
 * Fórmula del descuadre (la calcula `recalculate()`, nunca se escribe a mano):
 *  - countedCashTotal = Σ (denomination × quantity)               — lo físico contado
 *  - cashExpensesTotal = Σ expense.amount WHERE paymentMethod=CASH  — sólo lo pagado del cajón
 *  - expectedCash = baseAmount + salesCash − cashExpensesTotal      — lo que debería haber
 *  - overShort = countedCashTotal − expectedCash                   — descuadre (− = faltante)
 *
 * `payablesTotal` (Σ payable.totalAmount del día) es SÓLO INFORMATIVO: las
 * cuentas por pagar a proveedor no salen físicamente del cajón del día, así
 * que no entran al cálculo del descuadre.
 */
class CashSessionService
{
    /**
     * Abre (o devuelve, si ya existe) el cierre del día. Un cierre por
     * `businessDate`: no duplica.
     */
    public function openForDate(CarbonInterface|string $date, float $base, ?int $userId): CashSession
    {
        if ($base < 0) {
            throw new HttpException(422, 'La base del día no puede ser negativa.');
        }

        $businessDate = ($date instanceof CarbonInterface ? $date : Carbon::parse($date))->toDateString();

        return DB::transaction(function () use ($businessDate, $base, $userId) {
            $existing = CashSession::query()->whereDate('businessDate', $businessDate)->lockForUpdate()->first();

            if ($existing !== null) {
                return $existing;
            }

            return CashSession::create([
                'businessDate' => $businessDate,
                'baseAmount' => round($base, 2),
                'status' => CashSessionStatus::OPEN->value,
                'openedByUserId' => $userId,
            ]);
        });
    }

    /**
     * Guarda el borrador del cierre: escalares del día + sincroniza hijos
     * (denominaciones, gastos y cuentas por pagar). Recalcula al final.
     *
     * @param  array<string, mixed>  $data
     */
    public function saveDraft(CashSession $session, array $data): CashSession
    {
        return DB::transaction(function () use ($session, $data) {
            /** @var CashSession $session */
            $session = CashSession::whereKey($session->id)->lockForUpdate()->firstOrFail();

            if ($session->status === CashSessionStatus::CLOSED) {
                throw new HttpException(422, 'Este cierre del día ya está cerrado y no admite cambios.');
            }

            $scalars = array_intersect_key($data, array_flip([
                'baseAmount', 'salesCash', 'salesBank', 'salesNequi',
                'reportedSalesTotal', 'zNumber', 'zInvoiceCount', 'notes',
            ]));

            if ($scalars !== []) {
                $session->fill($scalars)->save();
            }

            if (array_key_exists('denominations', $data)) {
                $this->syncDenominations($session, $data['denominations']);
            }

            if (array_key_exists('expenses', $data)) {
                $this->syncExpenses($session, $data['expenses']);
            }

            if (array_key_exists('payables', $data)) {
                $this->syncPayables($session, $data['payables']);
            }

            $this->recalculate($session);

            return $session->refresh();
        });
    }

    /**
     * Recalcula y persiste `countedCashTotal`, `expensesTotal`, `payablesTotal`,
     * `expectedCash` y `overShort`. Ver la fórmula en la cabecera de la clase.
     */
    public function recalculate(CashSession $session): void
    {
        $countedCashTotal = (float) $session->denominations()
            ->get()
            ->sum(fn (CashDenomination $d) => $d->denomination * $d->quantity);

        $expensesTotal = (float) $session->expenses()->sum('amount');
        $payablesTotal = (float) $session->payables()->sum('totalAmount');

        $cashExpensesTotal = (float) $session->expenses()
            ->where('paymentMethod', PaymentMethod::CASH->value)
            ->sum('amount');

        $expectedCash = round((float) $session->baseAmount + (float) $session->salesCash - $cashExpensesTotal, 2);
        $overShort = round($countedCashTotal - $expectedCash, 2);

        $session->forceFill([
            'countedCashTotal' => round($countedCashTotal, 2),
            'expensesTotal' => round($expensesTotal, 2),
            'payablesTotal' => round($payablesTotal, 2),
            'expectedCash' => $expectedCash,
            'overShort' => $overShort,
        ])->save();
    }

    /** Cierra el cierre del día: recalcula y bloquea la edición. */
    public function close(CashSession $session, ?int $userId): CashSession
    {
        return DB::transaction(function () use ($session, $userId) {
            /** @var CashSession $session */
            $session = CashSession::whereKey($session->id)->lockForUpdate()->firstOrFail();

            if ($session->status === CashSessionStatus::CLOSED) {
                throw new HttpException(422, 'Este cierre del día ya está cerrado.');
            }

            $this->recalculate($session);
            $session->refresh();

            $session->forceFill([
                'status' => CashSessionStatus::CLOSED->value,
                'closedByUserId' => $userId,
                'closedAt' => now(),
            ])->save();

            return $session;
        });
    }

    /**
     * Reemplaza el arqueo por denominación con el enviado. `CashDenomination`
     * no tiene vida por fuera del cierre: se borra y se recrea entero.
     *
     * @param  array<int, array{denomination: int|string, quantity: int|string}>  $rows
     */
    private function syncDenominations(CashSession $session, array $rows): void
    {
        $session->denominations()->delete();

        foreach ($rows as $row) {
            $quantity = (int) $row['quantity'];

            if ($quantity <= 0) {
                continue;
            }

            CashDenomination::create([
                'cashSessionId' => $session->id,
                'denomination' => (int) $row['denomination'],
                'quantity' => $quantity,
            ]);
        }
    }

    /**
     * Crea/actualiza los `expense` ("nómina y otros") ligados a este cierre.
     * `Expense` es un registro financiero real (no propiedad exclusiva del
     * cierre): las filas que ya no vienen en el arreglo se DESLIGAN
     * (`cashSessionId = null`), nunca se borran.
     *
     * @param  array<int, array{id?: int|null, description: string, amount: float|string, category?: string|null, paymentMethod?: string|null}>  $rows
     */
    private function syncExpenses(CashSession $session, array $rows): void
    {
        $keptIds = [];

        foreach ($rows as $row) {
            $amount = round((float) $row['amount'], 2);

            if ($amount <= 0) {
                continue;
            }

            $payload = [
                'cashSessionId' => $session->id,
                'category' => $row['category'] ?? ExpenseCategory::NOMINA->value,
                'description' => $row['description'],
                'amount' => $amount,
                'expenseDate' => $session->businessDate->toDateString(),
                'paymentMethod' => $row['paymentMethod'] ?? PaymentMethod::CASH->value,
            ];

            $expense = null;

            if (! empty($row['id'])) {
                $expense = Expense::where('cashSessionId', $session->id)->whereKey($row['id'])->first();
            }

            if ($expense !== null) {
                $expense->update($payload);
            } else {
                $expense = Expense::create($payload);
            }

            $keptIds[] = $expense->id;
        }

        Expense::where('cashSessionId', $session->id)
            ->when($keptIds !== [], fn ($q) => $q->whereNotIn('id', $keptIds))
            ->update(['cashSessionId' => null]);
    }

    /**
     * Crea/actualiza las `payable` (cuentas por pagar) agrupadas en este
     * cierre. Igual que con `expense`: `Payable` es la fuente de verdad de
     * cartera, así que las que salen del arreglo se DESLIGAN, no se borran.
     *
     * @param  array<int, array{id?: int|null, supplierId: int, concept: string, invoiceNumber?: string|null, totalAmount: float|string, dueDate?: string|null}>  $rows
     */
    private function syncPayables(CashSession $session, array $rows): void
    {
        $keptIds = [];

        foreach ($rows as $row) {
            $totalAmount = round((float) $row['totalAmount'], 2);

            if ($totalAmount <= 0) {
                continue;
            }

            $payable = null;

            if (! empty($row['id'])) {
                $payable = Payable::where('cashSessionId', $session->id)->whereKey($row['id'])->first();
            }

            if ($payable !== null) {
                // Una cuenta ya con abonos no cambia su monto por el borrador del cierre.
                $payable->update([
                    'supplierId' => $row['supplierId'],
                    'concept' => $row['concept'],
                    'invoiceNumber' => $row['invoiceNumber'] ?? null,
                    'dueDate' => $row['dueDate'] ?? null,
                ]);
            } else {
                $payable = Payable::create([
                    'cashSessionId' => $session->id,
                    'supplierId' => $row['supplierId'],
                    'concept' => $row['concept'],
                    'invoiceNumber' => $row['invoiceNumber'] ?? null,
                    'issueDate' => $session->businessDate->toDateString(),
                    'dueDate' => $row['dueDate'] ?? null,
                    'totalAmount' => $totalAmount,
                    'paidAmount' => 0,
                ]);
            }

            $keptIds[] = $payable->id;
        }

        Payable::where('cashSessionId', $session->id)
            ->when($keptIds !== [], fn ($q) => $q->whereNotIn('id', $keptIds))
            ->update(['cashSessionId' => null]);
    }
}
