<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;
use App\Models\WarehouseType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Siembra de PRODUCCIÓN — arranque limpio.
 *
 * A diferencia de {@see DatabaseSeeder} (que carga catálogo, bodegas y usuarios
 * demo), este seeder deja el sistema listo para cargar los datos reales desde
 * cero. Sólo crea lo que la aplicación necesita para funcionar y que hoy no
 * tiene pantalla para crearse:
 *
 *   - la ficha de empresa (fila única que usa Company::current());
 *   - las unidades de medida (kg, unidad, …);
 *   - los tipos de bodega (refrigeración, congelación, seco, cuarentena);
 *   - la cuenta SUPERADMIN de soporte (credenciales desde .env).
 *
 * NO crea productos, categorías, proveedores, bodegas, lotes ni stock: todo eso
 * lo carga el negocio desde la aplicación.
 */
class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        // Empresa en blanco: sólo el nombre (obligatorio); el resto queda con
        // sus valores por defecto para que el cliente lo ajuste desde la app.
        Company::updateOrCreate(
            ['id' => 1],
            ['name' => 'Distribuidora'],
        );
        Company::forgetCurrent();

        // Unidades de medida: reutiliza el seeder de fundamentos.
        $this->call(UnitSeeder::class);

        // Tipos de bodega (sin crear bodegas: esas las crea el negocio).
        $types = [
            // [code, name, tempMin, tempMax, requiresColdChain]
            ['REFR', 'Refrigeración', 0, 4, true],
            ['CONG', 'Congelación', -18, -12, true],
            ['SECO', 'Almacenamiento seco', null, null, false],
            ['CUAR', 'Cuarentena', 0, 4, true],
        ];

        foreach ($types as [$code, $name, $min, $max, $coldChain]) {
            WarehouseType::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'defaultTempMin' => $min,
                    'defaultTempMax' => $max,
                    'requiresColdChain' => $coldChain,
                    'active' => true,
                ],
            );
        }

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
