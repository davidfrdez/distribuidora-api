<?php

namespace App\Support;

use App\Models\Lot;
use App\Models\StockMovement;

/**
 * Resultado de haber consumido de UN lote concreto.
 *
 * El FIFO puede repartir una salida entre varios lotes, así que devuelve una
 * lista de estas líneas. Es lo que después alimenta `order_item_lot` y permite
 * responder "de qué lotes salió este despacho".
 */
final readonly class ConsumptionLine
{
    public function __construct(
        public Lot $lot,
        public float $units,
        public float $kg,
        public float $cost,
        public StockMovement $movement,
    ) {}
}
