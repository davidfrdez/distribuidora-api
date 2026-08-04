<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\CodeGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function __construct(private readonly CodeGeneratorService $codeGenerator) {}

    /** GET /api/admin/products */
    public function index(Request $request): AnonymousResourceCollection
    {
        $products = Product::query()
            ->with(['category', 'purchaseUnit', 'saleUnit'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();
                $query->where(fn ($q) => $q
                    ->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('sku', 'LIKE', "%{$search}%")
                    ->orWhere('brand', 'LIKE', "%{$search}%"));
            })
            ->when($request->filled('categoryId'), fn ($q) => $q->where('categoryId', $request->integer('categoryId')))
            ->when($request->filled('saleMode'), fn ($q) => $q->where('saleMode', $request->string('saleMode')))
            ->when($request->filled('active'), fn ($q) => $q->where('active', $request->boolean('active')))
            ->when($request->boolean('sellableOnly'), fn ($q) => $q->where('sellable', true)->where('active', true))
            ->orderBy('displayOrder')
            ->orderBy('name')
            ->paginate($request->integer('perPage', 50));

        return ProductResource::collection($products);
    }

    /** GET /api/admin/products/{product} */
    public function show(Product $product): ProductResource
    {
        return new ProductResource(
            $product->load(['category', 'purchaseUnit', 'saleUnit', 'barcodes']),
        );
    }

    /**
     * POST /api/admin/products
     *
     * Si el cliente no manda `sku`, se autogenera a partir del nombre. La
     * creación queda dentro de la misma transacción que el generador de
     * códigos: su lockForUpdate() sólo evita colisiones si nadie más puede
     * insertar el producto entre que se calcula el consecutivo y se guarda.
     */
    public function store(ProductRequest $request): JsonResponse
    {
        $product = DB::transaction(function () use ($request) {
            $data = $request->payload();

            if (empty($data['sku'])) {
                $data['sku'] = $this->codeGenerator->generateBySlug($data['name'], 'product', 'sku', 3);
            }

            return Product::create($data);
        });

        return (new ProductResource($product->load(['category', 'purchaseUnit', 'saleUnit'])))
            ->response()
            ->setStatusCode(201);
    }

    /** PUT /api/admin/products/{product} */
    public function update(ProductRequest $request, Product $product): ProductResource
    {
        $product->update($request->payload());

        return new ProductResource($product->load(['category', 'purchaseUnit', 'saleUnit']));
    }

    /**
     * DELETE /api/admin/products/{product}
     *
     * Soft delete: el producto sale del catálogo pero los lotes y movimientos
     * históricos siguen apuntando a él, que es lo que sostiene la trazabilidad.
     */
    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json(['message' => 'Producto retirado del catálogo.']);
    }
}
