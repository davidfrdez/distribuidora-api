<?php

namespace App\Enums;

/**
 * Estado de un turno de caja.
 */
enum CashSessionStatus: string
{
    case OPEN = 'OPEN';       // caja abierta, admite movimientos
    case CLOSED = 'CLOSED';   // arqueada y cerrada

    public function isOpen(): bool
    {
        return $this === self::OPEN;
    }

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Abierta',
            self::CLOSED => 'Cerrada',
        };
    }
}
