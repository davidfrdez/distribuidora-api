<?php

namespace App\Services;

use App\Enums\PayableStatus;
use App\Enums\PaymentMethod;
use App\Models\Payable;
use App\Models\PayablePayment;
use App\Models\Supplier;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Cuentas por pagar a proveedores.
 *
 * Reglas que este servicio garantiza:
 *  1. `paidAmount` y `status` son consecuencia de los pagos: se recalculan en la
 *     misma transacción que inserta el pago, nunca se escriben a mano por fuera.
 *  2. No se puede pagar más que el saldo pendiente.
 *  3. Una cuenta anulada no recibe pagos.
 */
class PayableService
{
    /**
     * Registra una cuenta por pagar. El adjunto (foto de la factura) ya viene
     * guardado; aquí sólo se persiste su ruta.
     *
     * @param  array{invoiceNumber?: ?string, concept: string, totalAmount: float, dueDate?: ?string, notes?: ?string}  $data
     */
    public function register(
        Supplier $supplier,
        array $data,
        CarbonInterface $issueDate,
        ?CarbonInterface $dueDate = null,
        ?string $attachmentPath = null,
        ?int $userId = null,
    ): Payable {
        if ((float) $data['totalAmount'] <= 0) {
            throw new HttpException(422, 'El monto de la cuenta por pagar debe ser mayor que cero.');
        }

        return Payable::create([
            'supplierId' => $supplier->id,
            'invoiceNumber' => $data['invoiceNumber'] ?? null,
            'concept' => $data['concept'],
            'issueDate' => $issueDate->toDateString(),
            'dueDate' => $dueDate?->toDateString(),
            'totalAmount' => round((float) $data['totalAmount'], 2),
            'paidAmount' => 0,
            'status' => PayableStatus::PENDING->value,
            'attachmentPath' => $attachmentPath,
            'notes' => $data['notes'] ?? null,
            'createdById' => $userId,
        ]);
    }

    /**
     * Registra un pago (abono) contra una cuenta y actualiza saldo y estado.
     */
    public function pay(
        Payable $payable,
        float $amount,
        PaymentMethod $method,
        ?CarbonInterface $paymentDate = null,
        ?string $reference = null,
        ?string $notes = null,
        ?int $userId = null,
    ): PayablePayment {
        $amount = round($amount, 2);

        if ($amount <= 0) {
            throw new HttpException(422, 'El pago debe ser mayor que cero.');
        }

        return DB::transaction(function () use ($payable, $amount, $method, $paymentDate, $reference, $notes, $userId) {
            /** @var Payable $payable */
            $payable = Payable::whereKey($payable->id)->lockForUpdate()->firstOrFail();

            if (! $payable->status->isOpen()) {
                throw new HttpException(
                    409,
                    "La cuenta {$payable->concept} está {$payable->status->label()} y no admite más pagos.",
                );
            }

            $balance = $payable->balance();

            // Tolerancia de 1 centavo para no rechazar el pago que salda por redondeo.
            if ($amount > $balance + 0.01) {
                throw new HttpException(
                    409,
                    "El pago (\${$amount}) supera el saldo pendiente (\${$balance}).",
                );
            }

            $payment = PayablePayment::create([
                'payableId' => $payable->id,
                'amount' => $amount,
                'paymentDate' => ($paymentDate ?? now())->toDateString(),
                'paymentMethod' => $method->value,
                'reference' => $reference,
                'notes' => $notes,
                'createdById' => $userId,
            ]);

            $paid = round((float) $payable->paidAmount + $amount, 2);
            $isSettled = $paid + 0.01 >= (float) $payable->totalAmount;

            $payable->forceFill([
                'paidAmount' => $isSettled ? (float) $payable->totalAmount : $paid,
                'status' => $isSettled ? PayableStatus::PAID->value : PayableStatus::PARTIAL->value,
            ])->save();

            return $payment;
        });
    }

    /**
     * Anula una cuenta (factura errada, devolución total). No borra los pagos ya
     * hechos, sólo impide nuevos y la saca de "cuánto debo".
     */
    public function void(Payable $payable, string $reason, ?int $userId = null): Payable
    {
        return DB::transaction(function () use ($payable, $reason) {
            /** @var Payable $payable */
            $payable = Payable::whereKey($payable->id)->lockForUpdate()->firstOrFail();

            if ($payable->status === PayableStatus::VOID) {
                throw new HttpException(409, 'La cuenta ya está anulada.');
            }

            $payable->forceFill([
                'status' => PayableStatus::VOID->value,
                'notes' => trim(($payable->notes ?? '') . " | Anulada: {$reason}"),
            ])->save();

            return $payable;
        });
    }

    /**
     * Resumen de cartera por pagar para el tablero.
     *
     * @return array<string, mixed>
     */
    public function summary(?Carbon $today = null): array
    {
        $today ??= Carbon::today();
        $weekEnd = $today->copy()->addDays(7);

        $open = Payable::query()->open();

        $totalOwed = (float) (clone $open)->sum(DB::raw('totalAmount - paidAmount'));

        $overdue = (clone $open)
            ->whereNotNull('dueDate')
            ->whereDate('dueDate', '<', $today);

        $dueThisWeek = (clone $open)
            ->whereNotNull('dueDate')
            ->whereDate('dueDate', '>=', $today)
            ->whereDate('dueDate', '<=', $weekEnd);

        return [
            'totalOwed' => round($totalOwed, 2),
            'openCount' => (clone $open)->count(),
            'overdueCount' => (clone $overdue)->count(),
            'overdueAmount' => round((float) $overdue->sum(DB::raw('totalAmount - paidAmount')), 2),
            'dueThisWeekCount' => (clone $dueThisWeek)->count(),
            'dueThisWeekAmount' => round((float) $dueThisWeek->sum(DB::raw('totalAmount - paidAmount')), 2),
        ];
    }
}
