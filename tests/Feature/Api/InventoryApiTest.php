<?php

namespace Tests\Feature\Api;

use App\Enums\LotStatus;
use App\Enums\UserRole;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryApiTest extends TestCase
{
    use RefreshDatabase;

    private InventoryService $inventory;

    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inventory = app(InventoryService::class);
        $this->warehouse = Warehouse::factory()->create(['code' => 'CF-01']);
    }

    private function actingAsRole(UserRole $role): User
    {
        $user = User::factory()->role($role)->create();
        $this->actingAs($user);

        return $user;
    }

    // ── Recepción ────────────────────────────────────────────────────────────

    public function test_recibe_mercancia_y_crea_el_lote(): void
    {
        $this->actingAsRole(UserRole::ALMACENISTA);
        $chorizo = Product::factory()->byWeight()->create(['shelfLifeDays' => 30]);
        $proveedor = Supplier::factory()->create();

        $this->postJson('/api/admin/inventory/receive', [
            'productId' => $chorizo->id,
            'warehouseId' => $this->warehouse->id,
            'supplierId' => $proveedor->id,
            'units' => 20,
            'kg' => 12.5,
            'totalCost' => 300000,
            'supplierLotCode' => 'FAB-4471',
            'purchaseInvoice' => 'FV-9001',
        ])
            ->assertCreated()
            ->assertJsonPath('data.currentKg', '12.5000')
            ->assertJsonPath('data.currentUnits', '20.0000')
            ->assertJsonPath('data.supplierLotCode', 'FAB-4471')
            ->assertJsonPath('data.status', LotStatus::ACTIVE->value);

        $this->assertDatabaseCount('lot', 1);
        $this->assertDatabaseCount('stock_movement', 1);
    }

    public function test_recibir_peso_variable_sin_kilos_es_rechazado_por_el_dominio(): void
    {
        $this->actingAsRole(UserRole::ALMACENISTA);
        $chorizo = Product::factory()->byWeight()->create();

        // 422 del FormRequest si no viene ninguna cantidad…
        $this->postJson('/api/admin/inventory/receive', [
            'productId' => $chorizo->id,
            'warehouseId' => $this->warehouse->id,
            'totalCost' => 100000,
        ])->assertStatus(422)->assertJsonValidationErrors('units');

        // …y 422 del servicio si vienen unidades pero no kilos.
        $this->postJson('/api/admin/inventory/receive', [
            'productId' => $chorizo->id,
            'warehouseId' => $this->warehouse->id,
            'units' => 10,
            'totalCost' => 100000,
        ])->assertStatus(422);
    }

    public function test_rechaza_un_vencimiento_anterior_a_la_fabricacion(): void
    {
        $this->actingAsRole(UserRole::ALMACENISTA);
        $chorizo = Product::factory()->byWeight()->create();

        $this->postJson('/api/admin/inventory/receive', [
            'productId' => $chorizo->id,
            'warehouseId' => $this->warehouse->id,
            'units' => 10,
            'kg' => 5,
            'totalCost' => 100000,
            'manufacturingDate' => now()->subDays(5)->toDateString(),
            'expirationDate' => now()->subDays(10)->toDateString(),
        ])->assertStatus(422)->assertJsonValidationErrors('expirationDate');
    }

    // ── Consultas ────────────────────────────────────────────────────────────

    public function test_consulta_el_saldo_con_disponible_calculado(): void
    {
        $this->actingAsRole(UserRole::EMPACADOR);
        $chorizo = Product::factory()->byWeight()->create();
        $this->inventory->receive($chorizo, $this->warehouse, 20, 12.5, 300000);
        $this->inventory->reserve($chorizo, $this->warehouse, 5, 3, 'order', 1);

        $this->getJson('/api/admin/inventory/stock')
            ->assertOk()
            ->assertJsonPath('data.0.currentKg', '12.5000')
            ->assertJsonPath('data.0.reservedKg', '3.0000')
            ->assertJsonPath('data.0.availableKg', '9.5000');
    }

    public function test_lista_los_lotes_proximos_a_vencer(): void
    {
        $this->actingAsRole(UserRole::ALMACENISTA);
        $chorizo = Product::factory()->byWeight()->create(['shelfLifeDays' => null]);

        $this->inventory->receive($chorizo, $this->warehouse, 10, 5, 100000, expirationDate: now()->addDays(3));
        $this->inventory->receive($chorizo, $this->warehouse, 10, 5, 100000, expirationDate: now()->addDays(60));

        $this->getJson('/api/admin/inventory/lots?expiringInDays=7')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.daysToExpiration', 3);
    }

    public function test_el_kardex_devuelve_los_movimientos_con_saldos(): void
    {
        $this->actingAsRole(UserRole::ADMINISTRADOR);
        $chorizo = Product::factory()->byWeight()->create();
        $this->inventory->receive($chorizo, $this->warehouse, 20, 12.5, 300000);

        $this->getJson('/api/admin/inventory/kardex')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'PURCHASE')
            ->assertJsonPath('data.0.kgBefore', '0.0000')
            ->assertJsonPath('data.0.kgAfter', '12.5000')
            ->assertJsonPath('data.0.totalCost', '300000.00');
    }

    public function test_la_trazabilidad_de_un_lote_muestra_a_donde_fue_el_producto(): void
    {
        $this->actingAsRole(UserRole::ADMINISTRADOR);
        $chorizo = Product::factory()->byWeight()->create();
        $lote = $this->inventory->receive($chorizo, $this->warehouse, 20, 12.5, 300000);

        $this->inventory->consumeFifo(
            $chorizo, $this->warehouse, 2.14,
            \App\Enums\MovementType::SALE, 'order', 9001,
        );

        $this->getJson("/api/admin/inventory/lots/{$lote->id}/trace")
            ->assertOk()
            ->assertJsonPath('summary.receivedKg', 12.5)
            ->assertJsonPath('summary.dispatchedKg', 2.14)
            ->assertJsonPath('summary.remainingKg', 10.36)
            ->assertJsonPath('summary.references.0.type', 'order')
            ->assertJsonPath('summary.references.0.id', 9001)
            ->assertJsonPath('summary.references.0.kg', 2.14);
    }

    // ── Operaciones ──────────────────────────────────────────────────────────

    public function test_traslada_entre_bodegas(): void
    {
        $this->actingAsRole(UserRole::ALMACENISTA);
        $congelador = Warehouse::factory()->create(['code' => 'CG-01']);
        $chorizo = Product::factory()->byWeight()->create();
        $lote = $this->inventory->receive($chorizo, $this->warehouse, 20, 12.5, 300000);

        $this->postJson('/api/admin/inventory/transfer', [
            'lotId' => $lote->id,
            'destinationWarehouseId' => $congelador->id,
            'units' => 8,
            'kg' => 5,
        ])
            ->assertCreated()
            ->assertJsonPath('destinationLot.currentKg', '5.0000')
            ->assertJsonPath('destinationLot.warehouseId', $congelador->id);
    }

    public function test_registra_una_merma(): void
    {
        $this->actingAsRole(UserRole::EMPACADOR);
        $chorizo = Product::factory()->byWeight()->create();
        $lote = $this->inventory->receive($chorizo, $this->warehouse, 20, 12.5, 300000);

        $this->postJson('/api/admin/inventory/waste', [
            'lotId' => $lote->id,
            'units' => 1,
            'kg' => 0.5,
            'reason' => 'Empaque roto al descargar',
        ])
            ->assertCreated()
            ->assertJsonPath('movement.type', 'WASTE')
            ->assertJsonPath('movement.kg', '0.5000');

        $this->assertSame('12.0000', $lote->refresh()->currentKg);
    }

    public function test_un_ajuste_exige_motivo_y_solo_lo_hace_el_administrador(): void
    {
        $chorizo = Product::factory()->byWeight()->create();
        $lote = $this->inventory->receive($chorizo, $this->warehouse, 20, 12.5, 300000);

        // El almacenista puede recibir y trasladar, pero no ajustar: el ajuste es
        // la vía por la que se podría tapar un faltante.
        $this->actingAsRole(UserRole::ALMACENISTA);
        $this->postJson('/api/admin/inventory/adjust', [
            'lotId' => $lote->id,
            'unitsDelta' => 0,
            'kgDelta' => -0.5,
            'reason' => 'Conteo físico',
        ])->assertStatus(403);

        $this->actingAsRole(UserRole::ADMINISTRADOR);
        $this->postJson('/api/admin/inventory/adjust', [
            'lotId' => $lote->id,
            'unitsDelta' => 0,
            'kgDelta' => -0.5,
            'reason' => 'x',
        ])->assertStatus(422)->assertJsonValidationErrors('reason');

        $this->postJson('/api/admin/inventory/adjust', [
            'lotId' => $lote->id,
            'unitsDelta' => 0,
            'kgDelta' => -0.5,
            'reason' => 'Conteo físico del 31 de julio',
        ])->assertCreated();

        $this->assertSame('12.0000', $lote->refresh()->currentKg);
    }

    public function test_anula_un_lote_mal_recibido(): void
    {
        $this->actingAsRole(UserRole::ALMACENISTA);
        $chorizo = Product::factory()->byWeight()->create();
        $lote = $this->inventory->receive($chorizo, $this->warehouse, 20, 12.5, 300000);

        $this->postJson("/api/admin/inventory/lots/{$lote->id}/void", [
            'reason' => 'Llegó producto distinto al de la factura',
        ])
            ->assertOk()
            ->assertJsonPath('lot.status', LotStatus::VOID->value)
            ->assertJsonPath('lot.currentKg', '0.0000');
    }

    public function test_un_vendedor_no_puede_mover_inventario(): void
    {
        $this->actingAsRole(UserRole::VENDEDOR);
        $chorizo = Product::factory()->byWeight()->create();

        // Lee sin problema…
        $this->getJson('/api/admin/inventory/stock')->assertOk();

        // …pero no recibe mercancía.
        $this->postJson('/api/admin/inventory/receive', [
            'productId' => $chorizo->id,
            'warehouseId' => $this->warehouse->id,
            'units' => 10,
            'kg' => 5,
            'totalCost' => 100000,
        ])->assertStatus(403);
    }
}
