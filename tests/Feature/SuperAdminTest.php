<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SUPERADMIN: la cuenta de soporte/proveedor del software, por encima del
 * ADMINISTRADOR. No reintroduce multi-tenancy: sigue siendo un solo negocio,
 * sólo que con un nivel de soporte adicional que puede gestionar incluso a
 * los administradores.
 */
class SuperAdminTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsRole(UserRole $role): User
    {
        $user = User::factory()->role($role)->create();
        $this->actingAs($user);

        return $user;
    }

    public function test_el_superadmin_puede_iniciar_sesion(): void
    {
        User::factory()->role(UserRole::SUPERADMIN)->create(['email' => 'super@test.com']);

        $this->postJson('/api/auth/login', [
            'email' => 'super@test.com',
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('user.role', UserRole::SUPERADMIN->value)
            ->assertJsonPath('user.roleLabel', 'Superadministrador');
    }

    public function test_el_superadmin_puede_crear_un_administrador_y_otro_superadmin(): void
    {
        $this->actingAsRole(UserRole::SUPERADMIN);

        $this->postJson('/api/admin/users', [
            'name' => 'Nuevo Administrador',
            'email' => 'nuevo-admin@distribuidora.test',
            'password' => 'clave-segura-123',
            'role' => UserRole::ADMINISTRADOR->value,
        ])->assertCreated()->assertJsonPath('data.roleLabel', 'Administrador');

        $this->postJson('/api/admin/users', [
            'name' => 'Nuevo Superadmin',
            'email' => 'nuevo-super@distribuidora.test',
            'password' => 'clave-segura-123',
            'role' => UserRole::SUPERADMIN->value,
        ])->assertCreated()->assertJsonPath('data.roleLabel', 'Superadministrador');
    }

    public function test_un_administrador_no_puede_crear_un_superadmin(): void
    {
        $this->actingAsRole(UserRole::ADMINISTRADOR);

        $this->postJson('/api/admin/users', [
            'name' => 'Intento de Superadmin',
            'email' => 'intento@distribuidora.test',
            'password' => 'clave-segura-123',
            'role' => UserRole::SUPERADMIN->value,
        ])->assertStatus(403);

        $this->assertDatabaseMissing('user', ['email' => 'intento@distribuidora.test']);
    }

    public function test_un_administrador_no_puede_ascender_a_alguien_a_superadmin(): void
    {
        $this->actingAsRole(UserRole::ADMINISTRADOR);
        $vendedor = User::factory()->role(UserRole::VENDEDOR)->create();

        $this->putJson("/api/admin/users/{$vendedor->id}", [
            'role' => UserRole::SUPERADMIN->value,
        ])->assertStatus(403);

        $this->assertSame(UserRole::VENDEDOR, $vendedor->refresh()->role);
    }

    public function test_un_administrador_no_puede_editar_a_un_superadmin(): void
    {
        $this->actingAsRole(UserRole::ADMINISTRADOR);
        $super = User::factory()->role(UserRole::SUPERADMIN)->create(['name' => 'Original']);

        $this->putJson("/api/admin/users/{$super->id}", ['name' => 'Cambiado'])
            ->assertStatus(403);

        $this->assertSame('Original', $super->refresh()->name);
    }

    public function test_un_administrador_no_puede_desactivar_a_un_superadmin(): void
    {
        $this->actingAsRole(UserRole::ADMINISTRADOR);
        $super = User::factory()->role(UserRole::SUPERADMIN)->create();

        $this->deleteJson("/api/admin/users/{$super->id}")->assertStatus(403);

        $this->assertTrue($super->refresh()->active);
    }

    public function test_el_superadmin_si_puede_editar_a_otro_superadmin(): void
    {
        $this->actingAsRole(UserRole::SUPERADMIN);
        $otro = User::factory()->role(UserRole::SUPERADMIN)->create(['name' => 'Original']);

        $this->putJson("/api/admin/users/{$otro->id}", ['name' => 'Cambiado'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Cambiado');
    }

    public function test_no_deja_quedarse_sin_ningun_superadmin_activo(): void
    {
        $super = $this->actingAsRole(UserRole::SUPERADMIN);
        User::factory()->role(UserRole::ADMINISTRADOR)->create();

        // Es el único superadmin: no puede degradarse ni desactivarse.
        $this->putJson("/api/admin/users/{$super->id}", ['role' => UserRole::ADMINISTRADOR->value])
            ->assertStatus(422);

        $this->deleteJson("/api/admin/users/{$super->id}")->assertStatus(422);

        // Con un segundo superadmin, ya se puede.
        $segundo = User::factory()->role(UserRole::SUPERADMIN)->create();
        $this->putJson("/api/admin/users/{$super->id}", ['role' => UserRole::ADMINISTRADOR->value])
            ->assertOk();

        unset($segundo);
    }

    public function test_el_listado_de_roles_disponibles_no_incluye_superadmin_para_un_administrador(): void
    {
        $this->actingAsRole(UserRole::ADMINISTRADOR);

        $response = $this->getJson('/api/admin/users')->assertOk();

        $roles = collect($response->json('roles'))->pluck('value');
        $this->assertNotContains(UserRole::SUPERADMIN->value, $roles);
    }

    public function test_el_listado_de_roles_disponibles_incluye_superadmin_para_un_superadmin(): void
    {
        $this->actingAsRole(UserRole::SUPERADMIN);

        $response = $this->getJson('/api/admin/users')->assertOk();

        $roles = collect($response->json('roles'))->pluck('value');
        $this->assertContains(UserRole::SUPERADMIN->value, $roles);
    }

    public function test_el_seeder_crea_el_usuario_de_soporte_como_superadmin(): void
    {
        $this->seed(UserSeeder::class);

        $soporte = User::where('email', 'soporte@dalioss.com')->firstOrFail();

        $this->assertSame(UserRole::SUPERADMIN, $soporte->role);
        $this->assertTrue($soporte->active);
    }
}
