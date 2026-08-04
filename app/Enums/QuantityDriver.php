<?php

namespace App\Enums;

/**
 * Cuál de los dos saldos MANDA en un producto.
 *
 * El inventario lleva unidades y kg a la vez, pero en cada operación uno de los
 * dos es el que se pide y el otro es consecuencia. De un chorizo de peso
 * variable se piden kilos y las piezas salen detrás; de un queso de cabeza se
 * piden unidades y el peso no interviene.
 *
 * Sin esta distinción el FIFO no tiene forma de decidir cuánto sacar de cada
 * lote cuando los dos saldos se agotan a ritmos distintos.
 */
enum QuantityDriver: string
{
    case KG = 'KG';
    case UNITS = 'UNITS';

    public function label(): string
    {
        return match ($this) {
            self::KG => 'Kilogramos',
            self::UNITS => 'Unidades',
        };
    }
}
