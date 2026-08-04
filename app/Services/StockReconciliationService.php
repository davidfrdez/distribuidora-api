<?php

namespace App\Services;

use App\Models\Lot;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Support\StockDiscrepancy;
use Illuminate\Support\Collection;

/**
 * Compara las TRES fuentes de verdad del inventario:
 *
 *   1. `stock`                  → saldo desnormalizado (el que se consulta)
 *   2. `SUM(lot.current*)`      → suma de los lotes de la bodega
 *   3. `SUM(stock_movement)`    → el kardex, con signo según la dirección
 *
 * El sistema mantiene las tres en la misma transacción, así que en condiciones
 * normales deben coincidir exactamente. Que divergan significa que algo escribió
 * saldos por fuera del `InventoryService`, y descubrirlo tarde es carísimo:
 * DaliOrder tiene la misma triple redundancia pero ninguna verificación, de modo
 * que un descuadre sólo aparecía cuando alguien contaba a mano en la bodega.
 */
class StockReconciliationService
{
    /**
     * Tolerancia de comparación. El esquema guarda 4 decimales, así que
     * cualquier diferencia real es ≥ 0,0001.
     */
    private const EPSILON = 0.00005;

    /**
     * Revisa todas las combinaciones (producto, bodega) con saldo o movimientos.
     *
     * @return Collection<int, StockDiscrepancy>
     */
    public function findDiscrepancies(): Collection
    {
        $stocks = Stock::query()->with(['product', 'warehouse'])->get();

        $lotSums = Lot::query()
            ->selectRaw('productId, warehouseId, SUM(currentUnits) AS units, SUM(currentKg) AS kg')
            ->groupBy('productId', 'warehouseId')
            ->get()
            ->keyBy(fn ($row) => "{$row->productId}:{$row->warehouseId}");

        $movementSums = StockMovement::query()
            ->selectRaw(
                "productId, warehouseId,
                 SUM(CASE WHEN direction = 'IN' THEN units ELSE -units END) AS units,
                 SUM(CASE WHEN direction = 'IN' THEN kg ELSE -kg END) AS kg",
            )
            ->groupBy('productId', 'warehouseId')
            ->get()
            ->keyBy(fn ($row) => "{$row->productId}:{$row->warehouseId}");

        $discrepancies = new Collection;

        foreach ($stocks as $stock) {
            $key = "{$stock->productId}:{$stock->warehouseId}";

            $stockUnits = (float) $stock->currentUnits;
            $stockKg = (float) $stock->currentKg;
            $lotUnits = (float) ($lotSums[$key]->units ?? 0);
            $lotKg = (float) ($lotSums[$key]->kg ?? 0);
            $movementUnits = (float) ($movementSums[$key]->units ?? 0);
            $movementKg = (float) ($movementSums[$key]->kg ?? 0);

            $agree = $this->closeEnough($stockUnits, $lotUnits)
                && $this->closeEnough($stockUnits, $movementUnits)
                && $this->closeEnough($stockKg, $lotKg)
                && $this->closeEnough($stockKg, $movementKg);

            if ($agree) {
                continue;
            }

            $discrepancies->push(new StockDiscrepancy(
                productId: $stock->productId,
                warehouseId: $stock->warehouseId,
                productSku: (string) ($stock->product->sku ?? '?'),
                warehouseCode: (string) ($stock->warehouse->code ?? '?'),
                stockUnits: $stockUnits,
                lotUnits: $lotUnits,
                movementUnits: $movementUnits,
                stockKg: $stockKg,
                lotKg: $lotKg,
                movementKg: $movementKg,
            ));
        }

        return $discrepancies;
    }

    private function closeEnough(float $a, float $b): bool
    {
        return abs($a - $b) < self::EPSILON;
    }
}
