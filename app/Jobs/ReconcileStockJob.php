<?php

namespace App\Jobs;

use App\Services\StockReconciliationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Verificación diaria del inventario: contrasta `stock`, la suma de los lotes y
 * el kardex, y registra las divergencias.
 *
 * NO corrige nada a propósito. Un descuadre significa que algo escribió saldos
 * por fuera del `InventoryService`, y "arreglarlo" automáticamente ocultaría la
 * causa. La salida de este job es una alerta para que alguien investigue.
 */
class ReconcileStockJob implements ShouldQueue
{
    use Queueable;

    public function handle(StockReconciliationService $reconciliation): void
    {
        $discrepancies = $reconciliation->findDiscrepancies();

        if ($discrepancies->isEmpty()) {
            Log::info('Reconciliación de inventario: las tres fuentes cuadran.');

            return;
        }

        Log::error('Reconciliación de inventario: hay descuadres.', [
            'count' => $discrepancies->count(),
            'discrepancies' => $discrepancies->map->toArray()->all(),
        ]);
    }
}
