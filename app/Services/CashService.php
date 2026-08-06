<?php

namespace App\Services;

use App\Enums\CashMovementType;
use App\Enums\CashSessionStatus;
use App\Enums\MovementDirection;
use App\Models\CashMovement;
use App\Models\CashSession;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Caja: turnos de efectivo y su arqueo.
 *
 * Reglas:
 *  1. Sólo un turno abierto a la vez.
 *  2. Los movimientos sólo entran en un turno ABIERTO.
 *  3. El cierre calcula el esperado (base + ingresos − egresos) y lo compara
 *     con lo contado; la diferencia es el descuadre y se guarda para auditar.
 */
class CashService
{
    /** El turno abierto, o null si la caja está cerrada. */
    public function currentOpen(): ?CashSession
    {
        return CashSession::query()->where('status', CashSessionStatus::OPEN->value)->first();
    }

    /** Abre un turno con una base inicial. Falla si ya hay uno abierto. */
    public function open(int $userId, float $openingAmount, ?string $notes = null): CashSession
    {
        if ($openingAmount < 0) {
            throw new HttpException(422, 'La base de la caja no puede ser negativa.');
        }

        return DB::transaction(function () use ($userId, $openingAmount, $notes) {
            if ($this->currentOpen() !== null) {
                throw new HttpException(409, 'Ya hay una caja abierta. Ciérrala antes de abrir otra.');
            }

            return CashSession::create([
                'openedById' => $userId,
                'openingAmount' => round($openingAmount, 2),
                'openedAt' => now(),
                'status' => CashSessionStatus::OPEN->value,
                'notes' => $notes,
            ]);
        });
    }

    /** Registra un ingreso o egreso de efectivo en el turno abierto. */
    public function addMovement(
        CashSession $session,
        CashMovementType $type,
        float $amount,
        string $concept,
        ?int $userId = null,
    ): CashMovement {
        $amount = round($amount, 2);

        if ($amount <= 0) {
            throw new HttpException(422, 'El monto del movimiento debe ser mayor que cero.');
        }

        return DB::transaction(function () use ($session, $type, $amount, $concept, $userId) {
            /** @var CashSession $session */
            $session = CashSession::whereKey($session->id)->lockForUpdate()->firstOrFail();

            if (! $session->status->isOpen()) {
                throw new HttpException(409, 'La caja está cerrada: no admite movimientos.');
            }

            return CashMovement::create([
                'cashSessionId' => $session->id,
                'type' => $type->value,
                'direction' => $type->direction()->value,
                'amount' => $amount,
                'concept' => $concept,
                'createdById' => $userId,
            ]);
        });
    }

    /**
     * Cierra el turno: calcula el esperado, lo compara con lo contado y guarda
     * el descuadre.
     */
    public function close(CashSession $session, float $countedAmount, int $userId, ?string $notes = null): CashSession
    {
        $countedAmount = round($countedAmount, 2);

        if ($countedAmount < 0) {
            throw new HttpException(422, 'El efectivo contado no puede ser negativo.');
        }

        return DB::transaction(function () use ($session, $countedAmount, $userId, $notes) {
            /** @var CashSession $session */
            $session = CashSession::whereKey($session->id)->lockForUpdate()->firstOrFail();

            if (! $session->status->isOpen()) {
                throw new HttpException(409, 'La caja ya está cerrada.');
            }

            $expected = $this->expectedCash($session);
            $difference = round($countedAmount - $expected, 2);

            $session->forceFill([
                'status' => CashSessionStatus::CLOSED->value,
                'closedById' => $userId,
                'closingExpected' => $expected,
                'closingCounted' => $countedAmount,
                'difference' => $difference,
                'closedAt' => now(),
                'notes' => $notes !== null
                    ? trim(($session->notes ?? '') . " | Cierre: {$notes}")
                    : $session->notes,
            ])->save();

            return $session;
        });
    }

    /** Efectivo esperado en el cajón: base + ingresos − egresos. */
    public function expectedCash(CashSession $session): float
    {
        $in = (float) CashMovement::query()
            ->where('cashSessionId', $session->id)
            ->where('direction', MovementDirection::IN->value)
            ->sum('amount');

        $out = (float) CashMovement::query()
            ->where('cashSessionId', $session->id)
            ->where('direction', MovementDirection::OUT->value)
            ->sum('amount');

        return round((float) $session->openingAmount + $in - $out, 2);
    }

    /**
     * Totales del turno para mostrar en pantalla.
     *
     * @return array{openingAmount: float, totalIn: float, totalOut: float, expected: float}
     */
    public function totals(CashSession $session): array
    {
        $in = (float) CashMovement::query()
            ->where('cashSessionId', $session->id)
            ->where('direction', MovementDirection::IN->value)
            ->sum('amount');

        $out = (float) CashMovement::query()
            ->where('cashSessionId', $session->id)
            ->where('direction', MovementDirection::OUT->value)
            ->sum('amount');

        return [
            'openingAmount' => round((float) $session->openingAmount, 2),
            'totalIn' => round($in, 2),
            'totalOut' => round($out, 2),
            'expected' => round((float) $session->openingAmount + $in - $out, 2),
        ];
    }
}
