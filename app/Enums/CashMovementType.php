<?php

namespace App\Enums;

/**
 * Tipo de movimiento de EFECTIVO en la caja. El arqueo cuenta plata física, así
 * que la caja sólo registra efectivo (otros medios no pasan por el cajón).
 */
enum CashMovementType: string
{
    case INCOME = 'INCOME';         // ingreso: venta en efectivo, abono de cliente
    case EXPENSE = 'EXPENSE';       // egreso: pago o gasto menor en efectivo
    case WITHDRAWAL = 'WITHDRAWAL'; // retiro: plata que sale de la caja (al banco, al dueño)
    case DEPOSIT = 'DEPOSIT';       // ingreso de plata a la caja (fuera de ventas)

    /** Dirección del movimiento sobre el saldo de la caja. */
    public function direction(): MovementDirection
    {
        return match ($this) {
            self::INCOME, self::DEPOSIT => MovementDirection::IN,
            self::EXPENSE, self::WITHDRAWAL => MovementDirection::OUT,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::INCOME => 'Ingreso',
            self::EXPENSE => 'Egreso',
            self::WITHDRAWAL => 'Retiro',
            self::DEPOSIT => 'Consignación / ingreso',
        };
    }
}
