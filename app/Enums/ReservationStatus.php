<?php

namespace App\Enums;

/**
 * Estado de una reserva de stock. Existe para que dos pedidos no se lleven
 * el mismo lote durante el alistamiento, y para saber QUIÉN reservó qué.
 */
enum ReservationStatus: string
{
    case ACTIVE = 'ACTIVE';       // aparta stock; resta del disponible
    case CONSUMED = 'CONSUMED';   // se despachó: el stock ya salió del kardex
    case RELEASED = 'RELEASED';   // liberada a mano (pedido cancelado o editado)
    case EXPIRED = 'EXPIRED';     // liberada por vencimiento de `expiresAt`

    /** ¿Sigue restando del stock disponible? */
    public function holdsStock(): bool
    {
        return $this === self::ACTIVE;
    }

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Activa',
            self::CONSUMED => 'Consumida',
            self::RELEASED => 'Liberada',
            self::EXPIRED => 'Vencida',
        };
    }
}
