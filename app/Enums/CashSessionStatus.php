<?php

namespace App\Enums;

/**
 * Estado de un cierre de caja diario (arqueo).
 */
enum CashSessionStatus: string
{
    case OPEN = 'OPEN';       // en borrador: admite cambios
    case CLOSED = 'CLOSED';   // arqueado y cerrado: ya no admite cambios

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
