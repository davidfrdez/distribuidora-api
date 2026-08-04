<?php

namespace App\Enums;

/**
 * Signo de un movimiento del kardex. Las cantidades en `stock_movement` se
 * guardan siempre en positivo; la dirección es la que da el signo.
 */
enum MovementDirection: string
{
    case IN = 'IN';
    case OUT = 'OUT';

    /** +1 o -1, para aplicar la cantidad al saldo. */
    public function sign(): int
    {
        return $this === self::IN ? 1 : -1;
    }
}
