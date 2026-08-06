<?php

namespace App\Enums;

/**
 * Categoría de un gasto operativo del negocio. Es un enum fijo (no una tabla)
 * para no sumar otra pantalla de ABM en esta fase; si el negocio necesita
 * categorías propias más adelante, se migra a tabla sin romper los datos.
 */
enum ExpenseCategory: string
{
    case ASEO = 'ASEO';                   // jabón, champú, insumos de limpieza
    case SERVICIOS = 'SERVICIOS';         // luz, agua, internet
    case ARRIENDO = 'ARRIENDO';
    case TRANSPORTE = 'TRANSPORTE';       // combustible, fletes
    case NOMINA = 'NOMINA';               // pago de jornales (ver Fase 4)
    case MANTENIMIENTO = 'MANTENIMIENTO'; // neveras, equipos
    case IMPUESTOS = 'IMPUESTOS';
    case OTRO = 'OTRO';

    public function label(): string
    {
        return match ($this) {
            self::ASEO => 'Aseo',
            self::SERVICIOS => 'Servicios',
            self::ARRIENDO => 'Arriendo',
            self::TRANSPORTE => 'Transporte',
            self::NOMINA => 'Nómina / jornales',
            self::MANTENIMIENTO => 'Mantenimiento',
            self::IMPUESTOS => 'Impuestos',
            self::OTRO => 'Otro',
        };
    }
}
