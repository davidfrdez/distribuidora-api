<?php

namespace App\Enums;

/**
 * Medio por el que entra o sale la plata. Compartido por pagos a proveedores
 * (cuentas por pagar) y gastos; más adelante lo reusan caja y cobros.
 */
enum PaymentMethod: string
{
    case CASH = 'CASH';           // efectivo
    case NEQUI = 'NEQUI';         // billetera Nequi (muy usada en Colombia)
    case TRANSFER = 'TRANSFER';   // transferencia bancaria
    case CARD = 'CARD';           // datáfono / tarjeta

    public function label(): string
    {
        return match ($this) {
            self::CASH => 'Efectivo',
            self::NEQUI => 'Nequi',
            self::TRANSFER => 'Transferencia',
            self::CARD => 'Datáfono',
        };
    }
}
