<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Usuarios demo, uno por rol. Contraseña: `password`.
 *
 * SON SÓLO PARA DESARROLLO. Antes de instalar en la sede del cliente hay que
 * crear los usuarios reales y borrar estos.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Lista de tuplas, no un mapa: un enum no puede ser clave de array en PHP.
        $staff = [
            [UserRole::ADMINISTRADOR, 'Ana Administradora'],
            [UserRole::VENDEDOR, 'Vicente Vendedor'],
            [UserRole::CAJERO, 'Camila Cajera'],
            [UserRole::ALMACENISTA, 'Álvaro Almacenista'],
            [UserRole::EMPACADOR, 'Elena Empacadora'],
            [UserRole::MAQUILADOR, 'Mario Maquilador'],
            [UserRole::DOMICILIARIO, 'Diana Domiciliaria'],
        ];

        foreach ($staff as [$role, $name]) {
            User::updateOrCreate(
                ['email' => mb_strtolower($role->value) . '@distribuidora.test'],
                [
                    'name' => $name,
                    'role' => $role->value,
                    'password' => Hash::make('password'),
                    'active' => true,
                ],
            );
        }

        // Cuenta de soporte/proveedor del software: rol SUPERADMIN, por encima
        // del administrador del negocio.
        //
        // Las credenciales se leen de variables de entorno para NO dejar una
        // contraseña real en el repositorio. Se configuran en `.env`
        // (SUPERADMIN_EMAIL / SUPERADMIN_PASSWORD); `.env.example` sólo lleva un
        // placeholder. Si no están definidas, se usan valores de desarrollo.
        User::updateOrCreate(
            ['email' => env('SUPERADMIN_EMAIL', 'soporte@dalioss.com')],
            [
                'name' => 'Soporte DaliOSS',
                'role' => UserRole::SUPERADMIN->value,
                'password' => Hash::make(env('SUPERADMIN_PASSWORD', 'cambia-esta-clave')),
                'active' => true,
            ],
        );
    }
}
