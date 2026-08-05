<?php

namespace Tests\Feature\Api;

use App\Enums\SaleMode;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El usuario nunca debería tener que inventarse un código a mano: si no lo
 * manda, se autogenera. Si lo manda, se respeta.
 */
class AutoCodeTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsRole(UserRole $role): User
    {
        $user = User::factory()->role($role)->create();
        $this->actingAs($user);

        return $user;
    }

    // ── Producto ─────────────────────────────────────────────────────────────

    public function test_crea_un_producto_sin_sku_y_lo_autogenera_desde_el_nombre(): void
    {
        $this->actingAsRole(UserRole::ALMACENISTA);

        $this->postJson('/api/admin/products', [
            'name' => 'Chorizo Especial',
            'saleMode' => SaleMode::WEIGHT->value,
            'basePrice' => 32000,
            'netWeightKg' => 0.1,
        ])
            ->assertCreated()
            ->assertJsonPath('data.sku', 'CHO-001');
    }

    public function test_dos_productos_con_el_mismo_nombre_sin_sku_reciben_codigos_distintos(): void
    {
        $this->actingAsRole(UserRole::ALMACENISTA);

        $primero = $this->postJson('/api/admin/products', [
            'name' => 'Chorizo Especial',
            'saleMode' => SaleMode::WEIGHT->value,
            'basePrice' => 32000,
            'netWeightKg' => 0.1,
        ])->assertCreated();

        $segundo = $this->postJson('/api/admin/products', [
            'name' => 'Chorizo Especial',
            'saleMode' => SaleMode::WEIGHT->value,
            'basePrice' => 33000,
            'netWeightKg' => 0.1,
        ])->assertCreated();

        $this->assertSame('CHO-001', $primero->json('data.sku'));
        $this->assertSame('CHO-002', $segundo->json('data.sku'));
    }

    public function test_crea_un_producto_con_sku_explicito_y_lo_respeta(): void
    {
        $this->actingAsRole(UserRole::ALMACENISTA);

        $this->postJson('/api/admin/products', [
            'sku' => 'CHO-999',
            'name' => 'Chorizo Especial',
            'saleMode' => SaleMode::WEIGHT->value,
            'basePrice' => 32000,
            'netWeightKg' => 0.1,
        ])
            ->assertCreated()
            ->assertJsonPath('data.sku', 'CHO-999');
    }

    // ── Proveedor ────────────────────────────────────────────────────────────

    public function test_crea_un_proveedor_sin_code_y_lo_autogenera(): void
    {
        $this->actingAsRole(UserRole::ALMACENISTA);

        $this->postJson('/api/admin/suppliers', [
            'name' => 'Cárnicos del Llano S.A.S.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'PROV-001');
    }

    // ── Categoría ────────────────────────────────────────────────────────────

    public function test_crea_una_categoria_sin_code_y_lo_autogenera(): void
    {
        $this->actingAsRole(UserRole::ALMACENISTA);

        $this->postJson('/api/admin/categories', [
            'name' => 'Embutidos',
        ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'EMB-001');
    }
}
