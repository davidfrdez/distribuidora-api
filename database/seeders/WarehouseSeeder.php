<?php

namespace Database\Seeders;

use App\Models\Warehouse;
use App\Models\WarehouseType;
use Illuminate\Database\Seeder;

/**
 * Bodegas de arranque: los cuartos típicos de una salsamentaria más
 * la de cuarentena, que es obligatoria para poder retener devoluciones y
 * producto con la cadena de frío rota.
 */
class WarehouseSeeder extends Seeder
{
    public function run(): void
    {
        // [code, name, tempMin, tempMax, requiresColdChain]
        $types = [
            ['REFR', 'Refrigeración', 0, 4, true],
            ['CONG', 'Congelación', -18, -12, true],
            ['SECO', 'Almacenamiento seco', null, null, false],
            ['CUAR', 'Cuarentena', 0, 4, true],
        ];

        $typeIds = [];

        foreach ($types as [$code, $name, $min, $max, $coldChain]) {
            $type = WarehouseType::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'defaultTempMin' => $min,
                    'defaultTempMax' => $max,
                    'requiresColdChain' => $coldChain,
                    'active' => true,
                ],
            );

            $typeIds[$code] = $type->id;
        }

        // [code, name, typeCode, isDefault, isQuarantine, sellable]
        $warehouses = [
            ['CF-01', 'Cuarto frío principal', 'REFR', true, false, true],
            ['CG-01', 'Cuarto de congelación', 'CONG', false, false, true],
            ['SE-01', 'Bodega seca', 'SECO', false, false, true],
            ['DE-01', 'Zona de despacho', 'REFR', false, false, true],
            ['CU-01', 'Cuarentena', 'CUAR', false, true, false],
        ];

        foreach ($warehouses as [$code, $name, $typeCode, $isDefault, $isQuarantine, $sellable]) {
            Warehouse::updateOrCreate(
                ['code' => $code],
                [
                    'warehouseTypeId' => $typeIds[$typeCode],
                    'name' => $name,
                    // Sin rango propio: hereda el del tipo, así un cambio de
                    // política se aplica a todas las bodegas de ese tipo.
                    'tempMin' => null,
                    'tempMax' => null,
                    'isDefault' => $isDefault,
                    'isQuarantine' => $isQuarantine,
                    'sellable' => $sellable,
                    'active' => true,
                ],
            );
        }
    }
}
