<?php

namespace Tests\Feature\Api;

use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExpenseApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsRole(UserRole $role): User
    {
        $user = User::factory()->role($role)->create();
        $this->actingAs($user);

        return $user;
    }

    public function test_registra_un_gasto_con_soporte(): void
    {
        Storage::fake('local');
        $this->actingAsRole(UserRole::ADMINISTRADOR);

        $response = $this->post('/api/admin/expenses', [
            'category' => ExpenseCategory::ASEO->value,
            'description' => 'Jabón y champú para el local',
            'amount' => 45000,
            'expenseDate' => now()->toDateString(),
            'paymentMethod' => PaymentMethod::CASH->value,
            'attachment' => UploadedFile::fake()->image('recibo.jpg'),
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('data.categoryLabel', 'Aseo')
            ->assertJsonPath('data.hasAttachment', true);

        Storage::disk('local')->assertExists(Expense::firstOrFail()->attachmentPath);
    }

    public function test_el_resumen_agrupa_los_gastos_por_categoria(): void
    {
        $this->actingAsRole(UserRole::ADMINISTRADOR);

        Expense::factory()->create(['category' => ExpenseCategory::ASEO->value, 'amount' => 10000]);
        Expense::factory()->create(['category' => ExpenseCategory::ASEO->value, 'amount' => 20000]);
        Expense::factory()->create(['category' => ExpenseCategory::SERVICIOS->value, 'amount' => 5000]);

        $this->getJson('/api/admin/expenses/summary')
            ->assertOk()
            ->assertJsonPath('data.total', 35000)
            ->assertJsonPath('data.byCategory.0.category', 'ASEO')
            ->assertJsonPath('data.byCategory.0.total', 30000);
    }

    public function test_solo_finanzas_gestiona_gastos(): void
    {
        $this->actingAsRole(UserRole::ALMACENISTA);

        $this->getJson('/api/admin/expenses')->assertStatus(403);
        $this->postJson('/api/admin/expenses', [
            'category' => ExpenseCategory::ASEO->value,
            'description' => 'x',
            'amount' => 1000,
            'expenseDate' => now()->toDateString(),
            'paymentMethod' => PaymentMethod::CASH->value,
        ])->assertStatus(403);
    }
}
