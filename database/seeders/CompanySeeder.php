<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;

/**
 * Datos del negocio. UNA SOLA FILA — el sistema atiende a una distribuidora.
 * El cliente ajusta estos valores desde la aplicación.
 */
class CompanySeeder extends Seeder
{
    public function run(): void
    {
        Company::updateOrCreate(
            ['id' => 1],
            [
                'name' => 'El Dorado Distribuidora',
                // Razón social, NIT y dirección de abajo son de DEMO. Los reales
                // los carga el cliente desde la aplicación antes de facturar.
                'businessName' => 'El Dorado Distribuidora S.A.S.',
                'nit' => '900123456-7',
                'address' => 'Calle 22 # 25 - 32',
                'city' => 'Bogotá',
                'phone' => '6012345678',
                'whatsappPhone' => '3108049868',
                'email' => 'contacto@distribuidora.test',
                'timezone' => 'America/Bogota',
                'currency' => 'COP',
                'minOrderAmount' => 50000,
                'defaultWeightTolerancePercent' => 10,
                'reservationTtlMinutes' => 240,
            ],
        );

        Company::forgetCurrent();
    }
}
