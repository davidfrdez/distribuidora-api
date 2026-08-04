<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Login simple de un solo negocio: correo, contraseña y rol.
 * No hay sedes, tenants ni impersonación.
 */
class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_devuelve_token_y_payload_de_usuario(): void
    {
        $user = User::factory()->role(UserRole::CAJERO)->create(['email' => 'cajero@test.com']);

        $this->postJson('/api/auth/login', [
            'email' => 'cajero@test.com',
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.role', UserRole::CAJERO->value)
            ->assertJsonPath('user.roleLabel', 'Cajero')
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email', 'role', 'roleLabel', 'company'],
                'token',
                'token_type',
            ]);
    }

    public function test_el_payload_incluye_los_datos_del_negocio(): void
    {
        Company::factory()->create(['name' => 'Salsamentaria El Progreso', 'minOrderAmount' => 50000]);
        User::factory()->create(['email' => 'staff@test.com']);

        $this->postJson('/api/auth/login', [
            'email' => 'staff@test.com',
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('user.company.name', 'Salsamentaria El Progreso')
            ->assertJsonPath('user.company.minOrderAmount', '50000.00');
    }

    public function test_el_payload_no_expone_datos_de_tenant(): void
    {
        User::factory()->create(['email' => 'staff@test.com']);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'staff@test.com',
            'password' => 'password',
        ])->assertOk();

        // Regresión: el sistema no es multi-tenant y el payload no debe
        // reintroducir el concepto de sede por la puerta de atrás.
        $payload = $response->json('user');
        $this->assertArrayNotHasKey('locationId', $payload);
        $this->assertArrayNotHasKey('location', $payload);
        $this->assertArrayNotHasKey('managedLocations', $payload);
    }

    public function test_login_falla_con_credenciales_incorrectas(): void
    {
        User::factory()->create(['email' => 'alguien@test.com']);

        $this->postJson('/api/auth/login', [
            'email' => 'alguien@test.com',
            'password' => 'clave-mala',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_login_bloqueado_si_el_usuario_esta_inactivo(): void
    {
        User::factory()->inactive()->create(['email' => 'inactivo@test.com']);

        $this->postJson('/api/auth/login', [
            'email' => 'inactivo@test.com',
            'password' => 'password',
        ])->assertStatus(403)->assertJsonPath('error', 'USER_INACTIVE');
    }

    public function test_endpoint_protegido_requiere_autenticacion(): void
    {
        $this->getJson('/api/auth/user')->assertStatus(401);
    }

    public function test_me_devuelve_el_usuario_autenticado(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/auth/user')
            ->assertOk()
            ->assertJsonPath('user.email', $user->email);
    }

    public function test_el_header_de_tenant_es_ignorado(): void
    {
        $user = User::factory()->create();

        // Un cliente antiguo podría seguir enviándolo; no debe cambiar nada
        // ni provocar un error.
        $this->actingAs($user)
            ->withHeader('X-Tenant-Location', '99')
            ->getJson('/api/auth/user')
            ->assertOk()
            ->assertJsonPath('user.email', $user->email);
    }
}
