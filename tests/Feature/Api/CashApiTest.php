<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsRole(UserRole $role): User
    {
        $user = User::factory()->role($role)->create();
        $this->actingAs($user);

        return $user;
    }

    public function test_flujo_completo_abrir_mover_y_cerrar(): void
    {
        $this->actingAsRole(UserRole::CAJERO);

        // Sin caja abierta.
        $this->getJson('/api/admin/cash/current')->assertOk()->assertJsonPath('data', null);

        // Abrir con base.
        $this->postJson('/api/admin/cash/open', ['openingAmount' => 100000])
            ->assertCreated()
            ->assertJsonPath('data.status', 'OPEN')
            ->assertJsonPath('data.openingAmount', '100000.00');

        // Ingreso y egreso.
        $this->postJson('/api/admin/cash/movements', ['type' => 'INCOME', 'amount' => 200000, 'concept' => 'Ventas'])
            ->assertCreated()
            ->assertJsonPath('totals.expected', 300000);
        $this->postJson('/api/admin/cash/movements', ['type' => 'EXPENSE', 'amount' => 50000, 'concept' => 'Mensajero'])
            ->assertCreated()
            ->assertJsonPath('totals.expected', 250000);

        // Estado actual refleja el turno abierto.
        $this->getJson('/api/admin/cash/current')
            ->assertOk()
            ->assertJsonPath('data.session.status', 'OPEN')
            ->assertJsonPath('data.totals.expected', 250000);

        // Cerrar contando 245.000 → descuadre de −5.000.
        $this->postJson('/api/admin/cash/close', ['countedAmount' => 245000])
            ->assertOk()
            ->assertJsonPath('session.status', 'CLOSED')
            ->assertJsonPath('session.difference', '-5000.00');

        // Ya no hay caja abierta.
        $this->getJson('/api/admin/cash/current')->assertOk()->assertJsonPath('data', null);
    }

    public function test_no_se_puede_mover_sin_caja_abierta(): void
    {
        $this->actingAsRole(UserRole::CAJERO);

        $this->postJson('/api/admin/cash/movements', ['type' => 'INCOME', 'amount' => 1000, 'concept' => 'x'])
            ->assertStatus(409);
    }

    public function test_un_empacador_no_puede_operar_caja(): void
    {
        $this->actingAsRole(UserRole::EMPACADOR);

        $this->getJson('/api/admin/cash/current')->assertStatus(403);
        $this->postJson('/api/admin/cash/open', ['openingAmount' => 100000])->assertStatus(403);
    }
}
