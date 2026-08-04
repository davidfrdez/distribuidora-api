<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\Product;
use App\Models\TemperatureLog;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventorySummaryTest extends TestCase
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

    public function test_el_resumen_refleja_stock_alertas_y_lotes_por_vencer_tras_recibir_mercancia(): void
    {
        $this->actingAsRole(UserRole::ADMINISTRADOR);

        // Producto por debajo del mínimo configurado.
        $bajoMinimo = Product::factory()->byWeight()->create([
            'shelfLifeDays' => null,
            'minStockKg' => 100,
        ]);
        $this->inventory->receive($bajoMinimo, $this->warehouse, 20, 12.5, 300000);

        // Lote que vence pronto (dentro de la ventana de 7 días).
        $prontoAVencer = Product::factory()->byWeight()->create(['shelfLifeDays' => null]);
        $this->inventory->receive(
            $prontoAVencer,
            $this->warehouse,
            10,
            5,
            100000,
            expirationDate: now()->addDays(3),
        );

        // Lote que vence lejos: no debe contar como alerta.
        $venceLejos = Product::factory()->byWeight()->create(['shelfLifeDays' => null]);
        $this->inventory->receive(
            $venceLejos,
            $this->warehouse,
            10,
            5,
            100000,
            expirationDate: now()->addDays(60),
        );

        $response = $this->getJson('/api/admin/inventory/summary')->assertOk();

        $response->assertJsonPath('data.stock.totalProductsWithStock', 3);
        $response->assertJsonPath('data.stock.totalKg', 22.5);
        $response->assertJsonPath('data.stock.totalUnits', 40);

        $response->assertJsonPath('data.alerts.belowMinimum', 1);
        $response->assertJsonPath('data.alerts.expiringSoon', 1);

        $response->assertJsonCount(1, 'data.lowStock');
        $response->assertJsonPath('data.lowStock.0.sku', $bajoMinimo->sku);

        $response->assertJsonPath('data.recentMovements.0.type', 'PURCHASE');

        $movementsByType = $response->json('data.movementsByType7d');
        $this->assertCount(1, $movementsByType);
        $this->assertSame('PURCHASE', $movementsByType[0]['type']);
        $this->assertSame(3, $movementsByType[0]['count']);
    }

    public function test_cuenta_bien_los_lotes_proximos_a_vencer_frente_a_los_ya_vencidos(): void
    {
        $this->actingAsRole(UserRole::ALMACENISTA);

        $vencido = Product::factory()->byWeight()->create(['shelfLifeDays' => null]);
        $this->inventory->receive(
            $vencido,
            $this->warehouse,
            10,
            5,
            100000,
            expirationDate: now()->subDays(2),
        );

        $prontoAVencer = Product::factory()->byWeight()->create(['shelfLifeDays' => null]);
        $this->inventory->receive(
            $prontoAVencer,
            $this->warehouse,
            10,
            5,
            100000,
            expirationDate: now()->addDays(3),
        );

        $response = $this->getJson('/api/admin/inventory/summary')->assertOk();

        $response->assertJsonPath('data.alerts.expired', 1);
        $response->assertJsonPath('data.alerts.expiringSoon', 1);

        // El primero de `topExpiring` debe ser el que ya venció (orden ascendente).
        $response->assertJsonCount(2, 'data.topExpiring');
        $response->assertJsonPath('data.topExpiring.0.productName', $vencido->name);
        $this->assertLessThan(0, $response->json('data.topExpiring.0.daysToExpiration'));
        $response->assertJsonPath('data.topExpiring.1.productName', $prontoAVencer->name);
    }

    public function test_las_desviaciones_de_cadena_de_frio_solo_cuentan_las_ultimas_24_horas(): void
    {
        $this->actingAsRole(UserRole::ADMINISTRADOR);

        TemperatureLog::factory()->create([
            'warehouseId' => $this->warehouse->id,
            'outOfRange' => true,
            'recordedAt' => now()->subHours(2),
        ]);

        // Fuera de la ventana de 24 h: no debe contar.
        TemperatureLog::factory()->create([
            'warehouseId' => $this->warehouse->id,
            'outOfRange' => true,
            'recordedAt' => now()->subHours(30),
        ]);

        // Dentro de la ventana pero sin desviación: no debe contar.
        TemperatureLog::factory()->create([
            'warehouseId' => $this->warehouse->id,
            'outOfRange' => false,
            'recordedAt' => now()->subHours(1),
        ]);

        $this->getJson('/api/admin/inventory/summary')
            ->assertOk()
            ->assertJsonPath('data.alerts.coldChainDeviations24h', 1);
    }

    public function test_total_value_solo_lo_ve_quien_puede_ver_finanzas(): void
    {
        $chorizo = Product::factory()->byWeight()->create(['shelfLifeDays' => null]);
        $this->inventory->receive($chorizo, $this->warehouse, 20, 12.5, 300000);

        $this->actingAsRole(UserRole::ADMINISTRADOR);
        $this->getJson('/api/admin/inventory/summary')
            ->assertOk()
            ->assertJsonPath('data.stock.totalValue', 300000);

        $this->actingAsRole(UserRole::EMPACADOR);
        $response = $this->getJson('/api/admin/inventory/summary')->assertOk();
        $this->assertArrayNotHasKey('totalValue', $response->json('data.stock'));
    }

    public function test_requiere_autenticacion(): void
    {
        $this->getJson('/api/admin/inventory/summary')->assertStatus(401);
    }
}
