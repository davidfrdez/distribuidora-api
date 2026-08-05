<?php

namespace App\Services;

use App\Enums\LotStatus;
use App\Enums\MovementType;
use App\Models\Lot;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Métricas agregadas de inventario para el dashboard.
 *
 * Todo se calcula con consultas de agregación (COUNT/SUM/GROUP BY) contra la
 * base de datos: nunca se trae una tabla completa a memoria para sumarla en
 * PHP. Los listados (`topExpiring`, `lowStock`, `recentMovements`) sí traen
 * filas concretas, pero acotadas con `limit()`.
 */
class InventorySummaryService
{
    /** Ventana de alerta de vencimiento próximo, fija para el dashboard. */
    private const EXPIRING_SOON_DAYS = 7;

    private const TOP_EXPIRING_LIMIT = 5;

    private const LOW_STOCK_LIMIT = 20;

    private const RECENT_MOVEMENTS_LIMIT = 10;

    private const MOVEMENTS_BY_TYPE_WINDOW_DAYS = 7;

    /** @return array<string, mixed> */
    public function build(User $user): array
    {
        return [
            'stock' => $this->stockSummary($user),
            'alerts' => $this->alertsSummary(),
            'topExpiring' => $this->topExpiring(),
            'lowStock' => $this->lowStock(),
            'recentMovements' => $this->recentMovements(),
            'movementsByType7d' => $this->movementsByType(),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // STOCK
    // ─────────────────────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function stockSummary(User $user): array
    {
        $totals = DB::table('stock')
            ->join('product', 'product.id', '=', 'stock.productId')
            ->whereNull('product.deletedAt')
            ->where(fn ($q) => $q->where('stock.currentKg', '>', 0)->orWhere('stock.currentUnits', '>', 0))
            ->selectRaw('
                COUNT(DISTINCT stock.productId) AS productsWithStock,
                COALESCE(SUM(stock.currentKg), 0) AS totalKg,
                COALESCE(SUM(stock.currentUnits), 0) AS totalUnits
            ')
            ->first();

        $data = [
            'totalProductsWithStock' => (int) $totals->productsWithStock,
            'totalKg' => round((float) $totals->totalKg, 4),
            'totalUnits' => round((float) $totals->totalUnits, 4),
        ];

        // El valor del inventario es información financiera: sólo lo ve quien
        // puede ver costos, márgenes y cartera.
        if ($user->role->canSeeFinances()) {
            $data['totalValue'] = $this->totalStockValue();
        }

        return $data;
    }

    /**
     * Valorización de REFERENCIA del inventario, no el costo exacto de venta
     * (ese siempre sale del lote, FIFO). Por cada fila de `stock` se multiplica
     * el saldo que manda (kg o unidades, según el producto) por el costo
     * promedio ponderado del producto (`product.averageCostPer*`).
     */
    private function totalStockValue(): float
    {
        $row = DB::table('stock')
            ->join('product', 'product.id', '=', 'stock.productId')
            ->whereNull('product.deletedAt')
            ->selectRaw("
                COALESCE(SUM(
                    CASE
                        WHEN product.saleMode != 'UNIT' AND product.tracksWeight = 1
                            THEN stock.currentKg * product.averageCostPerKg
                        ELSE stock.currentUnits * product.averageCostPerUnit
                    END
                ), 0) AS totalValue
            ")
            ->first();

        return round((float) $row->totalValue, 2);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ALERTAS
    // ─────────────────────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function alertsSummary(): array
    {
        $today = Carbon::today();

        return [
            'belowMinimum' => $this->belowMinimumQuery()->count(),
            'expiringSoon' => $this->expiringSoonQuery($today)->count(),
            'expired' => $this->expiredQuery($today)->count(),
        ];
    }

    /** @return Builder<Stock> */
    private function belowMinimumQuery(): Builder
    {
        return Stock::query()->whereHas(
            'product',
            fn ($p) => $p->whereColumn('product.minStockKg', '>', 'stock.currentKg')
                ->orWhereColumn('product.minStockUnits', '>', 'stock.currentUnits'),
        );
    }

    /** @return Builder<Lot> */
    private function expiringSoonQuery(Carbon $today): Builder
    {
        return Lot::query()
            ->where('status', LotStatus::ACTIVE->value)
            ->withStock()
            ->whereNotNull('expirationDate')
            ->whereDate('expirationDate', '>=', $today)
            ->whereDate('expirationDate', '<=', $today->copy()->addDays(self::EXPIRING_SOON_DAYS));
    }

    /** @return Builder<Lot> */
    private function expiredQuery(Carbon $today): Builder
    {
        return Lot::query()
            ->withStock()
            ->whereNotNull('expirationDate')
            ->whereDate('expirationDate', '<', $today);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // LISTADOS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Los lotes con saldo que vencen primero, sin importar si ya vencieron:
     * es la lista de "qué revisar ya".
     *
     * @return list<array<string, mixed>>
     */
    private function topExpiring(): array
    {
        return Lot::query()
            ->with('product:id,name')
            ->withStock()
            ->whereNotNull('expirationDate')
            ->orderBy('expirationDate')
            ->orderBy('id')
            ->limit(self::TOP_EXPIRING_LIMIT)
            ->get()
            ->map(fn (Lot $lot) => [
                'code' => $lot->code,
                'productName' => $lot->product->name,
                'expirationDate' => $lot->expirationDate?->toDateString(),
                'daysToExpiration' => $lot->daysToExpiration(),
                'currentKg' => (float) $lot->currentKg,
                'currentUnits' => (float) $lot->currentUnits,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function lowStock(): array
    {
        return $this->belowMinimumQuery()
            ->with('product:id,sku,name,minStockKg,minStockUnits')
            ->orderBy('productId')
            ->limit(self::LOW_STOCK_LIMIT)
            ->get()
            ->map(fn (Stock $stock) => [
                'sku' => $stock->product->sku,
                'name' => $stock->product->name,
                'currentKg' => (float) $stock->currentKg,
                'minStockKg' => (float) $stock->product->minStockKg,
                'currentUnits' => (float) $stock->currentUnits,
                'minStockUnits' => (float) $stock->product->minStockUnits,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentMovements(): array
    {
        return StockMovement::query()
            ->with('product:id,name')
            ->orderByDesc('movementDate')
            ->orderByDesc('id')
            ->limit(self::RECENT_MOVEMENTS_LIMIT)
            ->get()
            ->map(fn (StockMovement $movement) => [
                'type' => $movement->type->value,
                'typeLabel' => $movement->type->label(),
                'direction' => $movement->direction->value,
                'productName' => $movement->product->name,
                'kg' => (float) $movement->kg,
                'units' => (float) $movement->units,
                'movementDate' => $movement->movementDate,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function movementsByType(): array
    {
        // Consulta contra el query builder plano (no el de Eloquent): así el
        // resultado no pasa por el cast `type => MovementType` del modelo y
        // `$row->type` llega como el string crudo que `MovementType::from()`
        // espera, en vez de una instancia de enum ya convertida.
        /** @var Collection<int, object{type: string, count: int, totalKg: float}> $rows */
        $rows = DB::table('stock_movement')
            ->where('movementDate', '>=', now()->subDays(self::MOVEMENTS_BY_TYPE_WINDOW_DAYS))
            ->selectRaw('type, COUNT(*) AS count, COALESCE(SUM(kg), 0) AS totalKg')
            ->groupBy('type')
            ->orderByDesc('count')
            ->get();

        return $rows
            ->map(fn ($row) => [
                'type' => $row->type,
                'typeLabel' => MovementType::from($row->type)->label(),
                'count' => (int) $row->count,
                'totalKg' => round((float) $row->totalKg, 4),
            ])
            ->all();
    }
}
