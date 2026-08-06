<?php

namespace Tests\Feature\Api;

use App\Enums\PayableStatus;
use App\Enums\UserRole;
use App\Models\Payable;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PayableApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsRole(UserRole $role): User
    {
        $user = User::factory()->role($role)->create();
        $this->actingAs($user);

        return $user;
    }

    public function test_registra_una_cuenta_con_foto_de_factura(): void
    {
        Storage::fake('local');
        $this->actingAsRole(UserRole::ADMINISTRADOR);
        $proveedor = Supplier::factory()->create();

        $response = $this->post('/api/admin/payables', [
            'supplierId' => $proveedor->id,
            'concept' => 'Queso campesino - pedido semanal',
            'invoiceNumber' => 'FV-8842',
            'totalAmount' => 480000,
            'issueDate' => now()->toDateString(),
            'dueDate' => now()->addDays(30)->toDateString(),
            'attachment' => UploadedFile::fake()->image('factura.jpg'),
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('data.concept', 'Queso campesino - pedido semanal')
            ->assertJsonPath('data.status', PayableStatus::PENDING->value)
            ->assertJsonPath('data.balance', 480000)
            ->assertJsonPath('data.hasAttachment', true);

        $this->assertDatabaseCount('payable', 1);
        // El archivo quedó guardado en el disco privado.
        $path = Payable::firstOrFail()->attachmentPath;
        $this->assertNotNull($path);
        Storage::disk('local')->assertExists($path);
    }

    public function test_rechaza_un_vencimiento_anterior_a_la_factura(): void
    {
        $this->actingAsRole(UserRole::ADMINISTRADOR);
        $proveedor = Supplier::factory()->create();

        $this->postJson('/api/admin/payables', [
            'supplierId' => $proveedor->id,
            'concept' => 'x',
            'totalAmount' => 1000,
            'issueDate' => now()->toDateString(),
            'dueDate' => now()->subDay()->toDateString(),
        ])->assertStatus(422)->assertJsonValidationErrors('dueDate');
    }

    public function test_registra_un_pago_y_actualiza_el_saldo(): void
    {
        $this->actingAsRole(UserRole::ADMINISTRADOR);
        $payable = Payable::factory()->create(['totalAmount' => 500000, 'paidAmount' => 0]);

        $this->postJson("/api/admin/payables/{$payable->id}/pay", [
            'amount' => 200000,
            'paymentMethod' => 'TRANSFER',
        ])
            ->assertCreated()
            ->assertJsonPath('payable.status', PayableStatus::PARTIAL->value)
            ->assertJsonPath('payable.balance', 300000);
    }

    public function test_el_resumen_responde_cuanto_debo(): void
    {
        $this->actingAsRole(UserRole::ADMINISTRADOR);
        Payable::factory()->create(['totalAmount' => 300000, 'status' => PayableStatus::PENDING->value]);

        $this->getJson('/api/admin/payables/summary')
            ->assertOk()
            ->assertJsonPath('data.totalOwed', 300000)
            ->assertJsonPath('data.openCount', 1);
    }

    public function test_sirve_la_factura_adjunta(): void
    {
        Storage::fake('local');
        $this->actingAsRole(UserRole::ADMINISTRADOR);

        Storage::disk('local')->put('payables/factura.jpg', 'contenido');
        $payable = Payable::factory()->create(['attachmentPath' => 'payables/factura.jpg']);

        $this->get("/api/admin/payables/{$payable->id}/invoice")->assertOk();
    }

    public function test_solo_finanzas_accede_a_las_cuentas_por_pagar(): void
    {
        $this->actingAsRole(UserRole::VENDEDOR);

        $this->getJson('/api/admin/payables')->assertStatus(403);
        $this->postJson('/api/admin/payables', [
            'supplierId' => Supplier::factory()->create()->id,
            'concept' => 'x',
            'totalAmount' => 1000,
            'issueDate' => now()->toDateString(),
        ])->assertStatus(403);
    }

    public function test_requiere_autenticacion(): void
    {
        $this->getJson('/api/admin/payables')->assertStatus(401);
    }
}
