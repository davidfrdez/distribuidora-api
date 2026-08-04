<?php

namespace App\Enums;

/**
 * Naturaleza física de una unidad de medida. Determina con qué saldo del
 * inventario se puede convertir: sólo se convierte entre unidades del mismo kind.
 */
enum UnitKind: string
{
    case WEIGHT = 'WEIGHT';   // kg, g, lb, arroba
    case COUNT = 'COUNT';     // unidad, paquete, canastilla, caja
    case VOLUME = 'VOLUME';   // l, ml — poco usado, pero existe en salmueras

    public function label(): string
    {
        return match ($this) {
            self::WEIGHT => 'Peso',
            self::COUNT => 'Conteo',
            self::VOLUME => 'Volumen',
        };
    }
}
