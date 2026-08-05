<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Siembra de PRODUCCIÓN — arranque limpio.
 *
 * A diferencia de {@see DatabaseSeeder} (que carga catálogo y usuarios demo),
 * este seeder deja el sistema listo para cargar los datos reales desde cero.
 * Sólo crea lo que la aplicación necesita para funcionar y que hoy no tiene
 * pantalla para crearse:
 *
 *   - la ficha de empresa (fila única que usa Company::current());
 *   - las unidades de medida (kg, libra, gramo, unidad);
 *   - la cuenta SUPERADMIN de soporte (credenciales desde .env).
 *
 * NO crea productos, categorías, proveedores, lotes ni stock: todo eso lo carga
 * el negocio desde la aplicación.
 */
class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        // Empresa en blanco: sólo el nombre (obligatorio); el resto queda con
        // sus valores por defecto para que el cliente lo ajuste desde la app.
        Company::updateOrCreate(
            ['id' => 1],
            ['name' => 'El Dorado Distribuidora'],
        );
        Company::forgetCurrent();

        // Unidades de medida: reutiliza el seeder de fundamentos.
        $this->call(UnitSeeder::class);

        // Única cuenta que arranca: el SUPERADMIN de soporte. Credenciales desde
        // .env (SUPERADMIN_EMAIL / SUPERADMIN_PASSWORD).
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
