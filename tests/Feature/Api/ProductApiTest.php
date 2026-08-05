<?php

namespace Tests\Feature\Api;

use App\Enums\SaleMode;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsRole(UserRole $role): User
    {
        $user = User::factory()->role($role)->create();
        $this->actingAs($user);

        return $user;
    }

    // ── Lectura ──────────────────────────────────────────────────────────────

    public function test_lista_productos_con_paginacion(): void
    {
        $this->actingAsRole(UserRole::VENDEDOR);
        Product::factory()->count(3)->create();

        $this->getJson('/api/admin/products')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure(['data' => [['id', 'sku', 'name', 'saleMode', 'driver', 'basePrice']], 'meta']);
    }

    public function test_busca_por_nombre_sku_y_marca(): void
    {
        $this->actingAsRole(UserRole::VENDEDOR);
        Product::factory()->create(['name' => 'Chorizo Santarrosano', 'sku' => 'CHO-004']);
        Product::factory()->create(['name' => 'Tocineta Ahumada', 'sku' => 'TOC-001']);

        $this->getJson('/api/admin/products?search=chorizo')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sku', 'CHO-004');

        $this->getJson('/api/admin/products?search=TOC')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sku', 'TOC-001');
    }

    public function test_filtra_por_categoria_y_modo_de_venta(): void
    {
        $this->actingAsRole(UserRole::VENDEDOR);
        $categoria = Category::factory()->create();

        Product::factory()->byWeight()->create(['categoryId' => $categoria->id]);
        Product::factory()->byUnit()->create();

        $this->getJson("/api/admin/products?categoryId={$categoria->id}")
            ->assertOk()->assertJsonCount(1, 'data');

        $this->getJson('/api/admin/products?saleMode=UNIT')
            ->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_el_costo_solo_se_expone_a_quien_puede_ver_finanzas(): void
    {
        $producto = Product::factory()->create(['averageCostPerKg' => 24000]);

        $this->actingAsRole(UserRole::ADMINISTRADOR);
        $this->getJson("/api/admin/products/{$producto->id}")
            ->assertOk()
            ->assertJsonPath('data.averageCostPerKg', '24000.0000');

        // Un empacador ve el producto pero no el costo: es el margen del negocio.
        $this->actingAsRole(UserRole::EMPACADOR);
        $response = $this->getJson("/api/admin/products/{$producto->id}")->assertOk();
        $this->assertArrayNotHasKey('averageCostPerKg', $response->json('data'));
        $this->assertArrayNotHasKey('lastCostPerKg', $response->json('data'));
    }

    // ── Escritura ────────────────────────────────────────────────────────────

    public function test_crea_un_producto_de_peso_variable(): void
    {
        $this->actingAsRole(UserRole::ALMACENISTA);

        $this->postJson('/api/admin/products', [
            'sku' => 'CHO-004',
            'name' => 'Chorizo Santarrosano',
            'saleMode' => SaleMode::WEIGHT->value,
            'basePrice' => 32000,
            'netWeightKg' => 0.1,
            'shelfLifeDays' => 30,
        ])
            ->assertCreated()
            ->assertJsonPath('data.sku', 'CHO-004')
            ->assertJsonPath('data.driver', 'KG')
            // `tracksWeight` se deriva del modo de venta, no lo manda el cliente.
            ->assertJsonPath('data.tracksWeight', true);
    }

    public function test_un_producto_por_unidad_queda_sin_peso(): void
    {
        $this->actingAsRole(UserRole::ALMACENISTA);

        // Aunque se envíe netWeightKg, un producto por unidad no lleva peso.
        $this->postJson('/api/admin/products', [
            'sku' => 'ESP-003',
            'name' => 'Queso de Cabeza',
            'saleMode' => SaleMode::UNIT->value,
            'basePrice' => 19500,
            'netWeightKg' => 1.5,
        ])
            ->assertCreated()
            ->assertJsonPath('data.driver', 'UNITS')
            ->assertJsonPath('data.tracksWeight', false)
            ->assertJsonPath('data.netWeightKg', null);
    }

    public function test_un_paquete_de_peso_fijo_exige_el_peso_neto(): void
    {
        $this->actingAsRole(UserRole::ALMACENISTA);

        $this->postJson('/api/admin/products', [
            'sku' => 'SAL-003',
            'name' => 'Paquete 500 g',
            'saleMode' => SaleMode::FIXED_PACK->value,
            'basePrice' => 14500,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('netWeightKg');
    }

    public function test_rechaza_sku_duplicado(): void
    {
        $this->actingAsRole(UserRole::ALMACENISTA);
        Product::factory()->create(['sku' => 'CHO-004']);

        $this->postJson('/api/admin/products', [
            'sku' => 'CHO-004',
            'name' => 'Otro',
            'saleMode' => SaleMode::WEIGHT->value,
            'basePrice' => 1000,
        ])->assertStatus(422)->assertJsonValidationErrors('sku');
    }

    public function test_actualiza_un_producto(): void
    {
        $this->actingAsRole(UserRole::ALMACENISTA);
        $producto = Product::factory()->create(['basePrice' => 30000]);

        $this->putJson("/api/admin/products/{$producto->id}", ['basePrice' => 32000])
            ->assertOk()
            ->assertJsonPath('data.basePrice', '32000.00');
    }

    public function test_el_borrado_es_logico_para_no_romper_la_trazabilidad(): void
    {
        $this->actingAsRole(UserRole::ALMACENISTA);
        $producto = Product::factory()->create();

        $this->deleteJson("/api/admin/products/{$producto->id}")->assertOk();

        $this->assertSoftDeleted('product', ['id' => $producto->id]);
        $this->getJson('/api/admin/products')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_ver_archivados_lista_solo_los_archivados(): void
    {
        $this->actingAsRole(UserRole::ALMACENISTA);
        $vivo = Product::factory()->create();
        $archivado = Product::factory()->create();
        $archivado->delete();

        // Sin el parámetro: sólo el vivo.
        $this->getJson('/api/admin/products')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $vivo->id);

        // Con archived=1: sólo el archivado.
        $this->getJson('/api/admin/products?archived=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $archivado->id);
    }

    public function test_reactivar_devuelve_el_producto_al_catalogo(): void
    {
        $this->actingAsRole(UserRole::ALMACENISTA);
        $producto = Product::factory()->create();
        $producto->delete();

        $this->postJson("/api/admin/products/{$producto->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.id', $producto->id);

        $this->assertDatabaseHas('product', ['id' => $producto->id, 'deletedAt' => null]);
        $this->getJson('/api/admin/products')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_reactivar_exige_permiso_de_inventario(): void
    {
        $producto = Product::factory()->create();
        $producto->delete();

        $this->actingAsRole(UserRole::VENDEDOR);
        $this->postJson("/api/admin/products/{$producto->id}/restore")->assertStatus(403);
    }

    // ── Permisos ─────────────────────────────────────────────────────────────

    public function test_un_vendedor_puede_leer_pero_no_escribir_el_catalogo(): void
    {
        $this->actingAsRole(UserRole::VENDEDOR);

        $this->getJson('/api/admin/products')->assertOk();

        $this->postJson('/api/admin/products', [
            'sku' => 'X-1',
            'name' => 'Producto',
            'saleMode' => SaleMode::WEIGHT->value,
            'basePrice' => 1000,
        ])->assertStatus(403)->assertJsonPath('error', 'FORBIDDEN');
    }

    public function test_sin_autenticacion_no_hay_acceso(): void
    {
        $this->getJson('/api/admin/products')->assertStatus(401);
    }
}
