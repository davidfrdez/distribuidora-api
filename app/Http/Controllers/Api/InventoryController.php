<?php

namespace App\Http\Controllers\Api;

use App\Enums\MovementDirection;
use App\Enums\MovementType;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReceiveStockRequest;
use App\Http\Resources\LotResource;
use App\Http\Resources\StockMovementResource;
use App\Http\Resources\StockResource;
use App\Models\Lot;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

/**
 * Operaciones de inventario. Todo el trabajo real lo hace `InventoryService`;
 * aquí sólo se resuelven los modelos y se traduce la respuesta.
 */
class InventoryController extends Controller
{
    public function __construct(private InventoryService $inventory) {}

    /** GET /api/admin/inventory/stock */
    public function stock(Request $request): AnonymousResourceCollection
    {
        $stock = Stock::query()
            ->with(['product.category', 'warehouse'])
            ->when($request->filled('productId'), fn ($q) => $q->where('productId', $request->integer('productId')))
            ->when($request->filled('warehouseId'), fn ($q) => $q->where('warehouseId', $request->integer('warehouseId')))
            ->when($request->boolean('withStockOnly'), fn ($q) => $q->where(
                fn ($sub) => $sub->where('currentUnits', '>', 0)->orWhere('currentKg', '>', 0),
            ))
            // Productos por debajo del mínimo configurado: la alerta de reposición.
            ->when($request->boolean('belowMinimum'), fn ($q) => $q->whereHas(
                'product',
                fn ($p) => $p->whereColumn('product.minStockKg', '>', 'stock.currentKg')
                    ->orWhereColumn('product.minStockUnits', '>', 'stock.currentUnits'),
            ))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();
                $query->whereHas('product', fn ($p) => $p
                    ->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('sku', 'LIKE', "%{$search}%"));
            })
            ->paginate($request->integer('perPage', 50));

        return StockResource::collection($stock);
    }

    /** GET /api/admin/inventory/lots */
    public function lots(Request $request): AnonymousResourceCollection
    {
        $lots = Lot::query()
            ->with(['product', 'warehouse', 'supplier'])
            ->when($request->filled('productId'), fn ($q) => $q->where('productId', $request->integer('productId')))
            ->when($request->filled('warehouseId'), fn ($q) => $q->where('warehouseId', $request->integer('warehouseId')))
            ->when($request->filled('supplierId'), fn ($q) => $q->where('supplierId', $request->integer('supplierId')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('supplierLotCode'), fn ($q) => $q->where('supplierLotCode', $request->string('supplierLotCode')))
            ->when($request->boolean('withStockOnly'), fn ($q) => $q->withStock())
            // Próximos a vencer: la consulta que evita perder producto.
            ->when($request->filled('expiringInDays'), fn ($q) => $q
                ->whereNotNull('expirationDate')
                ->whereDate('expirationDate', '<=', now()->addDays($request->integer('expiringInDays')))
                ->withStock())
            ->orderByRaw('expirationDate IS NULL ASC')
            ->orderBy('expirationDate')
            ->paginate($request->integer('perPage', 50));

        return LotResource::collection($lots);
    }

    /** GET /api/admin/inventory/kardex — libro mayor, el registro auditable. */
    public function kardex(Request $request): AnonymousResourceCollection
    {
        $movements = StockMovement::query()
            ->with(['product', 'warehouse', 'lot'])
            ->when($request->filled('productId'), fn ($q) => $q->where('productId', $request->integer('productId')))
            ->when($request->filled('warehouseId'), fn ($q) => $q->where('warehouseId', $request->integer('warehouseId')))
            ->when($request->filled('lotId'), fn ($q) => $q->where('lotId', $request->integer('lotId')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->when($request->filled('from'), fn ($q) => $q->where('movementDate', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->where('movementDate', '<=', $request->date('to')))
            ->orderByDesc('movementDate')
            ->orderByDesc('id')
            ->paginate($request->integer('perPage', 100));

        return StockMovementResource::collection($movements);
    }

    /**
     * GET /api/admin/inventory/lots/{lot}/trace
     *
     * Trazabilidad hacia adelante: a dónde fue a parar el producto de este lote.
     * Es la consulta que exige un retiro de producto y la razón por la que el
     * lote es obligatorio en cada movimiento.
     */
    public function traceLot(Lot $lot): JsonResponse
    {
        $lot->load(['product', 'warehouse', 'supplier']);

        $movements = $lot->movements()
            ->with(['warehouse'])
            ->orderBy('movementDate')
            ->get();

        return response()->json([
            'lot' => new LotResource($lot),
            'movements' => StockMovementResource::collection($movements),
            'summary' => [
                'receivedKg' => (float) $lot->initialKg,
                'receivedUnits' => (float) $lot->initialUnits,
                'remainingKg' => (float) $lot->currentKg,
                'remainingUnits' => (float) $lot->currentUnits,
                'dispatchedKg' => round((float) $lot->initialKg - (float) $lot->currentKg, 4),
                'dispatchedUnits' => round((float) $lot->initialUnits - (float) $lot->currentUnits, 4),
                // Documentos que recibieron producto de este lote. En la Fase 3,
                // cuando exista `order`, esto se resuelve al cliente concreto.
                'references' => $movements
                    ->filter(fn (StockMovement $m) => $m->direction === MovementDirection::OUT
                        && $m->referenceId !== null)
                    ->map(fn (StockMovement $m) => [
                        'type' => $m->referenceType,
                        'id' => $m->referenceId,
                        'kg' => (float) $m->kg,
                        'units' => (float) $m->units,
                        'date' => $m->movementDate,
                    ])
                    ->values(),
            ],
        ]);
    }

    /** POST /api/admin/inventory/receive */
    public function receive(ReceiveStockRequest $request): JsonResponse
    {
        $data = $request->validated();

        $lot = $this->inventory->receive(
            product: Product::findOrFail($data['productId']),
            warehouse: Warehouse::findOrFail($data['warehouseId']),
            units: (float) ($data['units'] ?? 0),
            kg: (float) ($data['kg'] ?? 0),
            totalCost: (float) $data['totalCost'],
            supplier: isset($data['supplierId']) ? Supplier::find($data['supplierId']) : null,
            supplierLotCode: $data['supplierLotCode'] ?? null,
            purchaseInvoice: $data['purchaseInvoice'] ?? null,
            expirationDate: isset($data['expirationDate']) ? Carbon::parse($data['expirationDate']) : null,
            manufacturingDate: isset($data['manufacturingDate']) ? Carbon::parse($data['manufacturingDate']) : null,
            receivedAt: isset($data['receivedAt']) ? Carbon::parse($data['receivedAt']) : null,
            userId: $request->user()->id,
            notes: $data['notes'] ?? null,
        );

        return (new LotResource($lot->load(['product', 'warehouse', 'supplier'])))
            ->response()
            ->setStatusCode(201);
    }

    /** POST /api/admin/inventory/transfer */
    public function transfer(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lotId' => ['required', 'integer', 'exists:lot,id'],
            'destinationWarehouseId' => ['required', 'integer', 'exists:warehouse,id'],
            'units' => ['required', 'numeric', 'min:0'],
            'kg' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $result = $this->inventory->transfer(
            lot: Lot::findOrFail($data['lotId']),
            destination: Warehouse::findOrFail($data['destinationWarehouseId']),
            units: (float) $data['units'],
            kg: (float) $data['kg'],
            userId: $request->user()->id,
            notes: $data['notes'] ?? null,
        );

        return response()->json([
            'message' => 'Traslado registrado.',
            'destinationLot' => new LotResource($result['lot']->load(['product', 'warehouse'])),
            'movements' => [
                'out' => new StockMovementResource($result['out']),
                'in' => new StockMovementResource($result['in']),
            ],
        ], 201);
    }

    /** POST /api/admin/inventory/adjust */
    public function adjust(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lotId' => ['required', 'integer', 'exists:lot,id'],
            'unitsDelta' => ['required', 'numeric'],
            'kgDelta' => ['required', 'numeric'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $movement = $this->inventory->adjust(
            lot: Lot::findOrFail($data['lotId']),
            unitsDelta: (float) $data['unitsDelta'],
            kgDelta: (float) $data['kgDelta'],
            reason: $data['reason'],
            userId: $request->user()->id,
        );

        return response()->json([
            'message' => 'Ajuste registrado.',
            'movement' => new StockMovementResource($movement->load(['product', 'warehouse', 'lot'])),
        ], 201);
    }

    /** POST /api/admin/inventory/waste — merma de un lote concreto. */
    public function waste(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lotId' => ['required', 'integer', 'exists:lot,id'],
            'units' => ['required', 'numeric', 'min:0'],
            'kg' => ['required', 'numeric', 'min:0'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $line = $this->inventory->consumeFromLot(
            lot: Lot::findOrFail($data['lotId']),
            units: (float) $data['units'],
            kg: (float) $data['kg'],
            type: MovementType::WASTE,
            referenceType: 'waste',
            referenceId: null,
            userId: $request->user()->id,
            notes: $data['reason'],
        );

        return response()->json([
            'message' => 'Merma registrada.',
            'movement' => new StockMovementResource($line->movement->load(['product', 'warehouse', 'lot'])),
        ], 201);
    }

    /** POST /api/admin/inventory/lots/{lot}/void */
    public function voidLot(Request $request, Lot $lot): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $lot = $this->inventory->voidLot($lot, $data['reason'], $request->user()->id);

        return response()->json([
            'message' => 'Lote anulado.',
            'lot' => new LotResource($lot->load(['product', 'warehouse'])),
        ]);
    }
}
