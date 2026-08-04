<?php

namespace Database\Seeders;

use App\Enums\UnitKind;
use App\Models\Unit;
use Illuminate\Database\Seeder;

/**
 * Unidades de medida del negocio. Una base por `kind`; el resto se expresa
 * con `factorToBase` para poder convertir sin tablas auxiliares.
 */
class UnitSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            // [code, name, kind, factorToBase, isBase, decimals]
            ['KG', 'Kilogramo', UnitKind::WEIGHT, 1, true, 3],
            ['G', 'Gramo', UnitKind::WEIGHT, 0.001, false, 0],
            ['LB', 'Libra', UnitKind::WEIGHT, 0.45359237, false, 3],
            ['ARR', 'Arroba', UnitKind::WEIGHT, 12.5, false, 2],
            ['UN', 'Unidad', UnitKind::COUNT, 1, true, 0],
            ['PAQ', 'Paquete', UnitKind::COUNT, 1, false, 0],
            ['CAN', 'Canastilla', UnitKind::COUNT, 1, false, 0],
            ['CAJ', 'Caja', UnitKind::COUNT, 1, false, 0],
            ['L', 'Litro', UnitKind::VOLUME, 1, true, 3],
            ['ML', 'Mililitro', UnitKind::VOLUME, 0.001, false, 0],
        ];

        foreach ($units as [$code, $name, $kind, $factor, $isBase, $decimals]) {
            Unit::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'kind' => $kind->value,
                    'factorToBase' => $factor,
                    'isBase' => $isBase,
                    'decimals' => $decimals,
                    'active' => true,
                ],
            );
        }
    }
}
