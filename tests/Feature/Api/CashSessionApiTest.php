<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\CashSession;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashSessionApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsRole(UserRole $role): User
    {
        $user = User::factory()->role($role)->create();
        $this->actingAs($user);

        return $user;
    }

    public function test_flujo_completo_abrir_guardar_leer_y_cerrar(): void
    {
        $this->actingAsRole(UserRole::CAJERO);
        $supplier = Supplier::factory()->create();

        // Abrir.
        $open = $this->postJson('/api/admin/cash-sessions', [
            'businessDate' => '2026-08-10',
            'baseAmount' => 289000,
        ])->assertCreated()
            ->assertJsonPath('data.status', 'OPEN')
            ->assertJsonPath('data.baseAmount', '289000.00');

        $id = $open->json('data.id');

        // Guardar borrador con hijos.
        $this->putJson("/api/admin/cash-sessions/{$id}", [
            'salesCash' => 706400,
            'denominations' => [
                ['denomination' => 100000, 'quantity' => 13],
                ['denomination' => 1000, 'quantity' => 1],
                ['denomination' => 200, 'quantity' => 2],
            ],
            'expenses' => [
                ['description' => 'Rosa Vargas', 'amount' => 296000, 'paymentMethod' => 'CASH'],
            ],
            'payables' => [
                ['supplierId' => $supplier->id, 'concept' => 'Queso campesino', 'totalAmount' => 480000],
            ],
        ])->assertOk()
            ->assertJsonPath('data.countedCashTotal', '1301400.00')
            ->assertJsonPath('data.expectedCash', '699400.00')
            ->assertJsonPath('data.overShort', '602000.00')
            ->assertJsonPath('data.payablesTotal', '480000.00');

        // Leer con hijos. `denominations` viene ordenado de menor a mayor.
        $this->getJson("/api/admin/cash-sessions/{$id}")
            ->assertOk()
            ->assertJsonPath('data.denominations.0.denomination', 200)
            ->assertJsonPath('data.denominations.0.quantity', 2)
            ->assertJsonPath('data.denominations.2.denomination', 100000)
            ->assertJsonPath('data.denominations.2.quantity', 13)
            ->assertJsonCount(3, 'data.denominations')
            ->assertJsonPath('data.expenses.0.description', 'Rosa Vargas')
            ->assertJsonPath('data.payables.0.concept', 'Queso campesino');

        // Cerrar.
        $this->postJson("/api/admin/cash-sessions/{$id}/close")
            ->assertOk()
            ->assertJsonPath('data.status', 'CLOSED')
            ->assertJsonPath('data.overShort', '602000.00');

        $this->assertNotNull(CashSession::find($id)->closedAt);

        // Ya cerrado: el PUT devuelve 422.
        $this->putJson("/api/admin/cash-sessions/{$id}", ['notes' => 'tarde'])
            ->assertStatus(422);
    }

    public function test_abrir_dos_veces_la_misma_fecha_no_duplica(): void
    {
        $this->actingAsRole(UserRole::CAJERO);

        $this->postJson('/api/admin/cash-sessions', ['businessDate' => '2026-08-10', 'baseAmount' => 100000])
            ->assertCreated();
        $this->postJson('/api/admin/cash-sessions', ['businessDate' => '2026-08-10', 'baseAmount' => 999999])
            ->assertCreated();

        $this->assertSame(1, CashSession::count());
    }

    public function test_current_devuelve_null_si_no_hay_cierre_ese_dia(): void
    {
        $this->actingAsRole(UserRole::CAJERO);

        $this->getJson('/api/admin/cash-sessions/current?date=2026-08-10')
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->postJson('/api/admin/cash-sessions', ['businessDate' => '2026-08-10', 'baseAmount' => 100000])
            ->assertCreated();

        $this->getJson('/api/admin/cash-sessions/current?date=2026-08-10')
            ->assertOk()
            ->assertJsonPath('data.businessDate', '2026-08-10');
    }

    public function test_un_cajero_puede_operar_y_un_vendedor_no(): void
    {
        $this->actingAsRole(UserRole::CAJERO);
        $this->postJson('/api/admin/cash-sessions', ['businessDate' => '2026-08-10', 'baseAmount' => 100000])
            ->assertCreated();

        $this->actingAsRole(UserRole::VENDEDOR);
        $this->getJson('/api/admin/cash-sessions')->assertStatus(403);
        $this->postJson('/api/admin/cash-sessions', ['businessDate' => '2026-08-11', 'baseAmount' => 100000])
            ->assertStatus(403);
    }

    public function test_requiere_autenticacion(): void
    {
        $this->getJson('/api/admin/cash-sessions')->assertStatus(401);
    }
}
