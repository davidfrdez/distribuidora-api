<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CashSessionController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\InventorySummaryController;
use App\Http\Controllers\Api\PayableController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API — Distribuidora
|--------------------------------------------------------------------------
| Sistema a la medida de un solo negocio: no hay multi-tenancy ni contexto de
| sede. El acceso se controla únicamente por rol, con el middleware `role`.
|
|   /auth/*          → autenticación (login público, el resto protegido)
|   /admin/*         → back-office
|   /integration/*   → API máquina-a-máquina para n8n / WhatsApp (Fase 6)
|
| Convención de permisos: la LECTURA del catálogo y del stock la necesita casi
| todo el personal (un empacador tiene que poder buscar un producto), así que
| basta estar autenticado. La ESCRITURA se restringe por rol.
|
| Convención de respuestas:
|   - Un recurso o colección → viene envuelto en `data` (+ `meta` si pagina).
|   - Una respuesta compuesta (varias entidades a la vez) → objeto con claves
|     nombradas y los recursos anidados SIN envoltorio `data`.
*/

// Roles agrupados para no repetir listas largas.
$soloAdmin = 'role:SUPERADMIN,ADMINISTRADOR';
$inventario = 'role:SUPERADMIN,ADMINISTRADOR,ALMACENISTA';

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->name('auth.login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('user', [AuthController::class, 'me'])->name('auth.user');
        Route::post('logout', [AuthController::class, 'logout'])->name('auth.logout');
    });
});

Route::middleware('auth:sanctum')->prefix('admin')->group(function () use ($soloAdmin, $inventario) {

    // ── Negocio y usuarios ───────────────────────────────────────────────────
    Route::get('company', [CompanyController::class, 'show']);
    Route::put('company', [CompanyController::class, 'update'])->middleware($soloAdmin);

    Route::middleware($soloAdmin)->group(function () {
        Route::get('users', [UserController::class, 'index']);
        Route::post('users', [UserController::class, 'store']);
        Route::put('users/{user}', [UserController::class, 'update']);
        Route::delete('users/{user}', [UserController::class, 'destroy']);
    });

    // ── Catálogo: lectura abierta, escritura restringida ─────────────────────
    Route::get('units', [CatalogController::class, 'units']);
    Route::get('categories', [CatalogController::class, 'categories']);
    Route::get('suppliers', [CatalogController::class, 'suppliers']);
    Route::get('suppliers/{supplier}/catalog', [CatalogController::class, 'supplierCatalog']);
    Route::get('products', [ProductController::class, 'index']);
    Route::get('products/{product}', [ProductController::class, 'show']);
    // `lookup/{code}` recibe el código escaneado, no un id: por eso va en una
    // ruta aparte de `barcodes/{barcode}`, que sí resuelve el modelo por id.
    Route::get('barcodes/lookup/{code}', [CatalogController::class, 'lookupBarcode']);

    Route::middleware($inventario)->group(function () {
        Route::post('units', [CatalogController::class, 'storeUnit']);
        Route::post('categories', [CatalogController::class, 'storeCategory']);
        Route::put('categories/{category}', [CatalogController::class, 'updateCategory']);
        Route::post('suppliers', [CatalogController::class, 'storeSupplier']);
        Route::put('suppliers/{supplier}', [CatalogController::class, 'updateSupplier']);

        Route::post('products', [ProductController::class, 'store']);
        Route::put('products/{product}', [ProductController::class, 'update']);
        Route::delete('products/{product}', [ProductController::class, 'destroy']);
        Route::post('products/{id}/restore', [ProductController::class, 'restore']);
        Route::post('products/{product}/barcodes', [CatalogController::class, 'storeBarcode']);
        Route::delete('barcodes/{barcode}', [CatalogController::class, 'destroyBarcode']);
    });

    // ── Inventario ───────────────────────────────────────────────────────────
    Route::prefix('inventory')->group(function () use ($inventario) {
        Route::get('summary', InventorySummaryController::class);
        Route::get('stock', [InventoryController::class, 'stock']);
        Route::get('lots', [InventoryController::class, 'lots']);
        Route::get('kardex', [InventoryController::class, 'kardex']);
        Route::get('lots/{lot}/trace', [InventoryController::class, 'traceLot']);

        Route::middleware($inventario)->group(function () {
            Route::post('receive', [InventoryController::class, 'receive']);
            Route::post('lots/{lot}/void', [InventoryController::class, 'voidLot']);
        });

        // Un ajuste corrige el saldo contra la realidad: sólo lo autoriza el
        // administrador (o el superadmin), porque es la vía por la que se
        // podría tapar un faltante.
        Route::post('adjust', [InventoryController::class, 'adjust'])->middleware('role:SUPERADMIN,ADMINISTRADOR');

        // La merma la reporta quien la encuentra en la operación.
        Route::post('waste', [InventoryController::class, 'waste'])
            ->middleware('role:SUPERADMIN,ADMINISTRADOR,ALMACENISTA,EMPACADOR,MAQUILADOR');
    });

    // ── Plata I: gastos y cuentas por pagar ──────────────────────────────────
    // Todo esto es información financiera sensible: sólo administrador/superadmin.
    Route::middleware($soloAdmin)->group(function () {
        // Cuentas por pagar
        Route::get('payables/summary', [PayableController::class, 'summary']);
        Route::get('payables', [PayableController::class, 'index']);
        Route::post('payables', [PayableController::class, 'store']);
        Route::get('payables/{payable}', [PayableController::class, 'show']);
        Route::get('payables/{payable}/invoice', [PayableController::class, 'invoice']);
        Route::post('payables/{payable}/pay', [PayableController::class, 'pay']);
        Route::post('payables/{payable}/void', [PayableController::class, 'void']);

        // Gastos
        Route::get('expenses/summary', [ExpenseController::class, 'summary']);
        Route::get('expenses', [ExpenseController::class, 'index']);
        Route::post('expenses', [ExpenseController::class, 'store']);
        Route::get('expenses/{expense}/support', [ExpenseController::class, 'support']);
    });

    // ── Caja: cierre diario (arqueo) ─────────────────────────────────────────
    // La opera el cajero además del administrador. `current` va ANTES del
    // wildcard `{cashSession}` para que no se interprete como un id.
    Route::middleware('role:SUPERADMIN,ADMINISTRADOR,CAJERO')->prefix('cash-sessions')->group(function () {
        Route::get('/', [CashSessionController::class, 'index']);
        Route::post('/', [CashSessionController::class, 'store']);
        Route::get('current', [CashSessionController::class, 'current']);
        Route::get('{cashSession}', [CashSessionController::class, 'show']);
        Route::put('{cashSession}', [CashSessionController::class, 'update']);
        Route::post('{cashSession}/close', [CashSessionController::class, 'close']);
    });
});
