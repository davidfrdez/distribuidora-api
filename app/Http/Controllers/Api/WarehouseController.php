<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\WarehouseRequest;
use App\Http\Resources\TemperatureLogResource;
use App\Http\Resources\WarehouseResource;
use App\Http\Resources\WarehouseTypeResource;
use App\Models\Warehouse;
use App\Models\WarehouseType;
use App\Services\CodeGeneratorService;
use App\Services\ColdChainService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class WarehouseController extends Controller
{
    public function __construct(
        private readonly ColdChainService $coldChain,
        private readonly CodeGeneratorService $codeGenerator,
    ) {}

    /** GET /api/admin/warehouses */
    public function index(Request $request): AnonymousResourceCollection
    {
        $warehouses = Warehouse::query()
            ->with('warehouseType')
            ->when($request->filled('active'), fn ($q) => $q->where('active', $request->boolean('active')))
            ->when($request->boolean('dispatchableOnly'), fn ($q) => $q
                ->where('active', true)->where('sellable', true)->where('isQuarantine', false))
            ->orderByDesc('isDefault')
            ->orderBy('code')
            ->get();

        return WarehouseResource::collection($warehouses);
    }

    /** GET /api/admin/warehouse-types */
    public function types(): AnonymousResourceCollection
    {
        return WarehouseTypeResource::collection(
            WarehouseType::orderBy('code')->get(),
        );
    }

    /** POST /api/admin/warehouses */
    public function store(WarehouseRequest $request): JsonResponse
    {
        $warehouse = $this->persist(new Warehouse, $request);

        return (new WarehouseResource($warehouse->load('warehouseType')))
            ->response()
            ->setStatusCode(201);
    }

    /** PUT /api/admin/warehouses/{warehouse} */
    public function update(WarehouseRequest $request, Warehouse $warehouse): WarehouseResource
    {
        return new WarehouseResource($this->persist($warehouse, $request)->load('warehouseType'));
    }

    /** GET /api/admin/warehouses/{warehouse}/temperatures */
    public function temperatures(Request $request, Warehouse $warehouse): AnonymousResourceCollection
    {
        $logs = $warehouse->temperatureLogs()
            ->when($request->boolean('deviationsOnly'), fn ($q) => $q->where('outOfRange', true))
            ->when($request->filled('from'), fn ($q) => $q->where('recordedAt', '>=', $request->date('from')))
            ->orderByDesc('recordedAt')
            ->paginate($request->integer('perPage', 100));

        return TemperatureLogResource::collection($logs);
    }

    /** POST /api/admin/warehouses/{warehouse}/temperatures */
    public function recordTemperature(Request $request, Warehouse $warehouse): JsonResponse
    {
        $data = $request->validate([
            'temperature' => ['required', 'numeric', 'min:-60', 'max:80'],
            'source' => ['nullable', 'in:MANUAL,SENSOR'],
            'notes' => ['nullable', 'string', 'max:300'],
        ]);

        $entry = $this->coldChain->record(
            warehouse: $warehouse,
            temperature: (float) $data['temperature'],
            recordedById: $request->user()->id,
            source: $data['source'] ?? 'MANUAL',
            notes: $data['notes'] ?? null,
        );

        return response()->json([
            'message' => $entry->outOfRange
                ? 'Lectura registrada FUERA DE RANGO. Revisa la bodega.'
                : 'Lectura registrada.',
            'log' => new TemperatureLogResource($entry),
            // Una desviación aislada suele ser la puerta abierta; lo que arruina
            // el producto es la que se mantiene. Se informa para que el
            // almacenista decida si hay que dar de baja mercancía.
            'sustainedBreach' => $entry->outOfRange
                ? $this->coldChain->hasSustainedBreach($warehouse, now()->subDay())
                : false,
        ], 201);
    }

    /**
     * Una sola bodega puede ser la predeterminada. Se resuelve en transacción
     * para que dos peticiones simultáneas no dejen dos marcadas. El código
     * sólo se autogenera al crear (`code` opcional); al editar se conserva
     * el existente si no llega uno nuevo.
     */
    private function persist(Warehouse $warehouse, WarehouseRequest $request): Warehouse
    {
        $data = $request->validated();
        $isNew = ! $warehouse->exists;

        return DB::transaction(function () use ($warehouse, $data, $isNew) {
            if ($isNew && empty($data['code'])) {
                $data['code'] = $this->codeGenerator->generateFixed('BOD', 'warehouse', 'code', 2);
            }

            $warehouse->fill($data)->save();

            if ($warehouse->isDefault) {
                Warehouse::whereKeyNot($warehouse->id)
                    ->where('isDefault', true)
                    ->update(['isDefault' => false]);
            }

            return $warehouse->refresh();
        });
    }
}
