<?php

namespace App\Enums;

/**
 * Cómo se vende y se factura un producto. Es la decisión estructural del sistema:
 * de aquí depende si el peso importa y cómo se calcula el total de una línea.
 */
enum SaleMode: string
{
    /**
     * Peso variable: precio por kg, el peso real se captura al alistar.
     * Ej. chorizo ahumado a $30.300/kg — el cliente pide 2 kg y se despachan 2,140.
     */
    case WEIGHT = 'WEIGHT';

    /**
     * Por unidad, el peso no interviene en el precio ni en el inventario.
     * Ej. queso de cabeza a $19.500 la unidad.
     */
    case UNIT = 'UNIT';

    /**
     * Paquete de peso fijo conocido: se cobra por unidad pero descuenta
     * `netWeightKg` de peso por cada unidad. Ej. bandeja de 500 g.
     */
    case FIXED_PACK = 'FIXED_PACK';

    /** ¿El inventario lleva saldo en kg para este modo? */
    public function tracksWeight(): bool
    {
        return $this !== self::UNIT;
    }

    /** ¿El precio se multiplica por kg (true) o por unidad (false)? */
    public function pricedByWeight(): bool
    {
        return $this === self::WEIGHT;
    }

    /**
     * Saldo que manda al pedir, vender o consumir.
     * De un producto de peso variable se piden kilos; de los otros dos, unidades.
     */
    public function driver(): QuantityDriver
    {
        return $this === self::WEIGHT ? QuantityDriver::KG : QuantityDriver::UNITS;
    }

    /**
     * ¿El peso se deriva de las unidades en vez de pesarse?
     * Sólo FIXED_PACK: kg = unidades × netWeightKg.
     */
    public function hasDerivedWeight(): bool
    {
        return $this === self::FIXED_PACK;
    }

    public function label(): string
    {
        return match ($this) {
            self::WEIGHT => 'Peso variable (por kg)',
            self::UNIT => 'Por unidad',
            self::FIXED_PACK => 'Paquete de peso fijo',
        };
    }
}
