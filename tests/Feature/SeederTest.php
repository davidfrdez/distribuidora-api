<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_seed_crea_una_sola_fila_de_negocio(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, Company::count());
        $this->assertSame('Distribuidora de Salsamentaria', Company::current()->name);
    }

    public function test_el_seed_crea_un_usuario_por_rol(): void
    {
        $this->seed(DatabaseSeeder::class);

        $usuarios = User::all();
        $this->assertCount(count(UserRole::cases()), $usuarios);

        foreach (UserRole::cases() as $role) {
            $this->assertTrue(
                $usuarios->contains(fn (User $u) => $u->role === $role),
                "Falta el usuario demo del rol {$role->value}.",
            );
        }
    }

    public function test_el_seed_es_idempotente(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, Company::count());
        $this->assertSame(count(UserRole::cases()), User::count());
    }
}
