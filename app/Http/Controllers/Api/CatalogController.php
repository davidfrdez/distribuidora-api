<?php

namespace App\Http\Controllers\Api;

use App\Enums\UnitKind;
use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductBarcodeResource;
use App\Http\Resources\ProductResource;
use App\Http\Resources\SupplierResource;
use App\Http\Resources\UnitResource;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\Supplier;
use App\Models\Unit;
use App\Services\CodeGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Entidades de apoyo del catálogo: unidades, categorías, códigos de barras y
 * proveedores. Son CRUDs simples sin lógica de dominio, agrupados en un solo
 * controlador para no multiplicar clases de diez líneas.
 */
class CatalogController extends Controller
{
    public function __construct(private readonly CodeGeneratorService $codeGenerator) {}

    // ── Unidades ─────────────────────────────────────────────────────────────

    /** GET /api/admin/units */
    public function units(Request $request): AnonymousResourceCollection
    {
        $units = Unit::query()
            ->when($request->filled('kind'), fn ($q) => $q->where('kind', $request->string('kind')))
            ->when($request->boolean('activeOnly'), fn ($q) => $q->where('active', true))
            ->orderBy('kind')
            ->orderByDesc('isBase')
            ->orderBy('code')
            ->get();

        return UnitResource::collection($units);
    }

    /** POST /api/admin/units */
    public function storeUnit(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:unit,code'],
            'name' => ['required', 'string', 'max:60'],
            'kind' => ['required', Rule::enum(UnitKind::class)],
            'factorToBase' => ['required', 'numeric', 'gt:0'],
            'decimals' => ['integer', 'min:0', 'max:6'],
        ]);

        // `isBase` no se acepta del cliente: la base de cada naturaleza la fija
        // el seeder y cambiarla recalcularía mal todas las conversiones.
        $unit = Unit::create($data + ['isBase' => false, 'active' => true]);

        return (new UnitResource($unit))->response()->setStatusCode(201);
    }

    // ── Categorías ───────────────────────────────────────────────────────────

    /** GET /api/admin/categories */
    public function categories(Request $request): AnonymousResourceCollection
    {
        $categories = Category::query()
            ->withCount('products')
            ->when($request->boolean('rootOnly'), fn ($q) => $q->whereNull('parentId')->with('children'))
            ->when($request->boolean('activeOnly'), fn ($q) => $q->where('active', true))
            ->orderBy('displayOrder')
            ->orderBy('name')
            ->get();

        return CategoryResource::collection($categories);
    }

    /**
     * POST /api/admin/categories
     *
     * `code` es opcional: si no llega, se autogenera a partir del nombre.
     */
    public function storeCategory(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:20', 'unique:category,code'],
            'name' => ['required', 'string', 'max:120'],
            'parentId' => ['nullable', 'integer', 'exists:category,id'],
            'description' => ['nullable', 'string', 'max:300'],
            'displayOrder' => ['integer', 'min:0'],
        ]);

        $category = DB::transaction(function () use ($data) {
            if (empty($data['code'])) {
                $data['code'] = $this->codeGenerator->generateBySlug($data['name'], 'category', 'code', 3);
            }

            return Category::create($data);
        });

        return (new CategoryResource($category))->response()->setStatusCode(201);
    }

    /** PUT /api/admin/categories/{category} */
    public function updateCategory(Request $request, Category $category): CategoryResource
    {
        $data = $request->validate([
            'code' => ['sometimes', 'string', 'max:20', Rule::unique('category', 'code')->ignore($category->id)],
            'name' => ['sometimes', 'string', 'max:120'],
            // No puede ser su propio padre: crearía un ciclo en el árbol.
            'parentId' => ['nullable', 'integer', 'exists:category,id', Rule::notIn([$category->id])],
            'description' => ['nullable', 'string', 'max:300'],
            'displayOrder' => ['integer', 'min:0'],
            'active' => ['boolean'],
        ]);

        $category->update($data);

        return new CategoryResource($category);
    }

    // ── Códigos de barras ────────────────────────────────────────────────────

    /** POST /api/admin/products/{product}/barcodes */
    public function storeBarcode(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'barcode' => ['required', 'string', 'max:60', 'unique:product_barcode,barcode'],
            'label' => ['nullable', 'string', 'max:60'],
            'isWeightEmbedded' => ['boolean'],
            'isPrimary' => ['boolean'],
        ]);

        $barcode = $product->barcodes()->create($data);

        if ($barcode->isPrimary) {
            $product->barcodes()->whereKeyNot($barcode->id)->update(['isPrimary' => false]);
        }

        return (new ProductBarcodeResource($barcode))->response()->setStatusCode(201);
    }

    /** DELETE /api/admin/barcodes/{barcode} */
    public function destroyBarcode(ProductBarcode $barcode): JsonResponse
    {
        $barcode->delete();

        return response()->json(['message' => 'Código de barras eliminado.']);
    }

    /** GET /api/admin/barcodes/lookup/{code} — resolver un escaneo a producto. */
    public function lookupBarcode(string $code): JsonResponse
    {
        $match = ProductBarcode::with('product')->where('barcode', $code)->first();

        if ($match === null) {
            return response()->json([
                'error' => 'BARCODE_NOT_FOUND',
                'message' => "El código {$code} no está asociado a ningún producto.",
            ], 404);
        }

        return response()->json([
            'barcode' => new ProductBarcodeResource($match),
            'product' => new ProductResource($match->product),
        ]);
    }

    // ── Proveedores ──────────────────────────────────────────────────────────

    /** GET /api/admin/suppliers */
    public function suppliers(Request $request): AnonymousResourceCollection
    {
        $suppliers = Supplier::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();
                $query->where(fn ($q) => $q
                    ->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('nit', 'LIKE', "%{$search}%")
                    ->orWhere('code', 'LIKE', "%{$search}%"));
            })
            ->when($request->boolean('activeOnly'), fn ($q) => $q->where('active', true))
            ->orderBy('name')
            ->paginate($request->integer('perPage', 50));

        return SupplierResource::collection($suppliers);
    }

    /**
     * POST /api/admin/suppliers
     *
     * `code` es opcional: si no llega, se autogenera con prefijo fijo "PROV".
     */
    public function storeSupplier(Request $request): JsonResponse
    {
        $data = $this->supplierRules($request);

        $supplier = DB::transaction(function () use ($data) {
            if (empty($data['code'])) {
                $data['code'] = $this->codeGenerator->generateFixed('PROV', 'supplier', 'code', 3);
            }

            return Supplier::create($data);
        });

        return (new SupplierResource($supplier))->response()->setStatusCode(201);
    }

    /** PUT /api/admin/suppliers/{supplier} */
    public function updateSupplier(Request $request, Supplier $supplier): SupplierResource
    {
        $supplier->update($this->supplierRules($request, $supplier));

        return new SupplierResource($supplier);
    }

    /** @return array<string, mixed> */
    private function supplierRules(Request $request, ?Supplier $supplier = null): array
    {
        $required = $supplier === null ? 'required' : 'sometimes';

        // En creación es 'nullable' (storeSupplier() autogenera si falta); en
        // edición es 'sometimes' para no pisar con null el code existente
        // cuando el cliente no lo envía.
        $codeRule = $supplier === null ? 'nullable' : 'sometimes';

        return $request->validate([
            'code' => [$codeRule, 'string', 'max:20', Rule::unique('supplier', 'code')->ignore($supplier?->id)],
            'name' => [$required, 'string', 'max:200'],
            'nit' => ['nullable', 'string', 'max:30'],
            'contactName' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:190'],
            'address' => ['nullable', 'string', 'max:250'],
            'city' => ['nullable', 'string', 'max:100'],
            'invimaRegistration' => ['nullable', 'string', 'max:60'],
            'paymentTermDays' => ['integer', 'min:0', 'max:365'],
            'notes' => ['nullable', 'string', 'max:500'],
            'active' => ['boolean'],
        ]);
    }
}
