<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsRole(UserRole $role): User
    {
        $user = User::factory()->role($role)->create();
        $this->actingAs($user);

        return $user;
    }

    public function test_lista_bodegas_con_el_rango_efectivo_de_temperatura(): void
    {
        $this->actingAsRole(UserRole::ALMACENISTA);
        $tipo = WarehouseType::factory()->freezer()->create();
        Warehouse::factory()->withoutOwnRange()->create(['warehouseTypeId' => $tipo->id]);

        $this->getJson('/api/admin/warehouses')
            ->assertOk()
            // La bodega no define rango propio: hereda el del tipo congelación.
            ->assertJsonPath('data.0.tempMin', null)
            // JSON no distingue -18.0 de -18: se compara con el entero.
            ->assertJsonPath('data.0.effectiveTempMin', -18)
            ->assertJsonPath('data.0.effectiveTempMax', -12);
    }

    public function test_filtra_las_bodegas_que_pueden_despachar(): void
    {
        $this->actingAsRole(UserRole::EMPACADOR);
        Warehouse::factory()->create(['code' => 'CF-01']);
        Warehouse::factory()->quarantine()->create();

        $this->getJson('/api/admin/warehouses?dispatchableOnly=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'CF-01');
    }

    public function test_crea_una_bodega(): void
    {
        $this->actingAsRole(UserRole::ALMACENISTA);

        $this->postJson('/api/admin/warehouses', [
            'code' => 'CG-01',
            'name' => 'Cuarto de congelación',
            'tempMin' => -18,
            'tempMax' => -12,
        ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'CG-01')
            ->assertJsonPath('data.canDispatch', true);
    }

    public function test_una_bodega_de_cuarentena_no_puede_ser_vendible(): void
    {
        $this->actingAsRole(UserRole::ALMACENISTA);

        $this->postJson('/api/admin/warehouses', [
            'code' => 'CU-01',
            'name' => 'Cuarentena',
            'isQuarantine' => true,
            'sellable' => true,
        ])->assertStatus(422)->assertJsonValidationErrors('sellable');
    }

    public function test_solo_una_bodega_queda_como_predeterminada(): void
    {
        $this->actingAsRole(UserRole::ALMACENISTA);
        $primera = Warehouse::factory()->create(['code' => 'A', 'isDefault' => true]);

        $this->postJson('/api/admin/warehouses', [
            'code' => 'B',
            'name' => 'Nueva principal',
            'isDefault' => true,
        ])->assertCreated();

        $this->assertFalse($primera->refresh()->isDefault);
        $this->assertSame(1, Warehouse::where('isDefault', true)->count());
    }

    public function test_rechaza_un_rango_de_temperatura_invertido(): void
    {
        $this->actingAsRole(UserRole::ALMACENISTA);

        $this->postJson('/api/admin/warehouses', [
            'code' => 'X',
            'name' => 'Bodega',
            'tempMin' => 10,
            'tempMax' => 0,
        ])->assertStatus(422)->assertJsonValidationErrors('tempMax');
    }

    // ── Cadena de frío ───────────────────────────────────────────────────────

    public function test_registra_una_lectura_dentro_de_rango(): void
    {
        $this->actingAsRole(UserRole::EMPACADOR);
        $bodega = Warehouse::factory()->create(['tempMin' => 0, 'tempMax' => 4]);

        $this->postJson("/api/admin/warehouses/{$bodega->id}/temperatures", ['temperature' => 2.5])
            ->assertCreated()
            ->assertJsonPath('log.outOfRange', false)
            ->assertJsonPath('sustainedBreach', false);
    }

    public function test_una_lectura_fuera_de_rango_avisa(): void
    {
        $this->actingAsRole(UserRole::ALMACENISTA);
        $bodega = Warehouse::factory()->create(['tempMin' => 0, 'tempMax' => 4]);

        $this->postJson("/api/admin/warehouses/{$bodega->id}/temperatures", ['temperature' => 11])
            ->assertCreated()
            ->assertJsonPath('log.outOfRange', true)
            ->assertJsonPath('message', 'Lectura registrada FUERA DE RANGO. Revisa la bodega.');
    }

    public function test_lista_solo_las_desviaciones_cuando_se_pide(): void
    {
        $this->actingAsRole(UserRole::ALMACENISTA);
        $bodega = Warehouse::factory()->create(['tempMin' => 0, 'tempMax' => 4]);

        $this->postJson("/api/admin/warehouses/{$bodega->id}/temperatures", ['temperature' => 2]);
        $this->postJson("/api/admin/warehouses/{$bodega->id}/temperatures", ['temperature' => 12]);

        $this->getJson("/api/admin/warehouses/{$bodega->id}/temperatures?deviationsOnly=1")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.temperature', '12.00');
    }

    public function test_un_vendedor_no_registra_temperaturas_ni_crea_bodegas(): void
    {
        $this->actingAsRole(UserRole::VENDEDOR);
        $bodega = Warehouse::factory()->create();

        $this->postJson("/api/admin/warehouses/{$bodega->id}/temperatures", ['temperature' => 2])
            ->assertStatus(403);

        $this->postJson('/api/admin/warehouses', ['code' => 'X', 'name' => 'Bodega'])
            ->assertStatus(403);
    }
}
