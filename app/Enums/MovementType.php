<?php

namespace App\Enums;

/**
 * Motivo de un movimiento del kardex. `stock_movement` es INMUTABLE: un error
 * se corrige con un movimiento contrario, nunca editando el original.
 */
enum MovementType: string
{
    case PURCHASE = 'PURCHASE';                 // recepción de compra
    case SALE = 'SALE';                         // despacho a cliente
    case RETURN_FROM_CUSTOMER = 'RETURN_FROM_CUSTOMER';
    case RETURN_TO_SUPPLIER = 'RETURN_TO_SUPPLIER';
    case WASTE = 'WASTE';                       // merma
    case ADJUSTMENT_IN = 'ADJUSTMENT_IN';       // ajuste manual o por conteo
    case ADJUSTMENT_OUT = 'ADJUSTMENT_OUT';
    case PRODUCTION_IN = 'PRODUCTION_IN';       // salida de maquila/despiece
    case PRODUCTION_OUT = 'PRODUCTION_OUT';     // insumo consumido por la maquila
    case VOID_LOT = 'VOID_LOT';                 // anulación de un lote mal recibido

    /** Dirección natural del tipo. */
    public function direction(): MovementDirection
    {
        return match ($this) {
            self::PURCHASE,
            self::RETURN_FROM_CUSTOMER,
            self::ADJUSTMENT_IN,
            self::PRODUCTION_IN => MovementDirection::IN,

            self::SALE,
            self::RETURN_TO_SUPPLIER,
            self::WASTE,
            self::ADJUSTMENT_OUT,
            self::PRODUCTION_OUT,
            self::VOID_LOT => MovementDirection::OUT,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::PURCHASE => 'Compra',
            self::SALE => 'Venta',
            self::RETURN_FROM_CUSTOMER => 'Devolución de cliente',
            self::RETURN_TO_SUPPLIER => 'Devolución a proveedor',
            self::WASTE => 'Merma',
            self::ADJUSTMENT_IN => 'Ajuste (entrada)',
            self::ADJUSTMENT_OUT => 'Ajuste (salida)',
            self::PRODUCTION_IN => 'Producción (entrada)',
            self::PRODUCTION_OUT => 'Producción (consumo)',
            self::VOID_LOT => 'Anulación de lote',
        };
    }
}
