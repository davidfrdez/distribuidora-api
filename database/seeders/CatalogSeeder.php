<?php

namespace Database\Seeders;

use App\Enums\SaleMode;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Database\Seeder;

/**
 * Catálogo de arranque con referencias reales de salsamentaria.
 *
 * Los NOMBRES y PRECIOS provienen del catálogo público de una distribuidora del
 * sector (fasaga.com.co, consultado 2026-07-31), para tener datos con la forma
 * y la escala del negocio real.
 *
 * Los parámetros de operación —vida útil, merma diaria por deshidratación, stock
 * mínimo— son ESTIMACIONES razonables para demo. El cliente debe ajustarlos con
 * sus datos antes de operar.
 */
class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $units = Unit::pluck('id', 'code');
        $categories = $this->seedCategories();

        // [sku, nombre, categoría, saleMode, precio, netWeightKg, vidaÚtilDías, mermaDiaria%]
        $products = [
            // ── Chorizos ─────────────────────────────────────────────────────
            ['CHO-001', 'Chorizo Ahumado', 'CHORIZOS', SaleMode::WEIGHT, 30300, 0.080, 45, 0.08],
            ['CHO-002', 'Chorizo Coctel', 'CHORIZOS', SaleMode::WEIGHT, 31300, 0.025, 45, 0.08],
            ['CHO-003', 'Chorizo Parrillero', 'CHORIZOS', SaleMode::WEIGHT, 30300, 0.120, 30, 0.10],
            ['CHO-004', 'Chorizo Santarrosano', 'CHORIZOS', SaleMode::WEIGHT, 32000, 0.100, 30, 0.10],

            // ── Salchichas y salchichón ──────────────────────────────────────
            ['SAL-001', 'Salchichón Cervecero', 'SALCHICHAS', SaleMode::WEIGHT, 11000, 1.000, 60, 0.05],
            ['SAL-002', 'Salchicha Manguera', 'SALCHICHAS', SaleMode::UNIT, 21600, null, 45, 0.00],
            // Referencia de demostración del tercer modo de venta: paquete de peso fijo.
            ['SAL-003', 'Salchicha Coctel Paquete 500 g', 'SALCHICHAS', SaleMode::FIXED_PACK, 14500, 0.500, 60, 0.00],

            // ── Jamones ──────────────────────────────────────────────────────
            ['JAM-001', 'Jamón Pullman', 'JAMONES', SaleMode::WEIGHT, 32400, 3.000, 45, 0.06],
            ['JAM-002', 'Jamón de Cerdo Batido', 'JAMONES', SaleMode::WEIGHT, 56000, 3.500, 45, 0.06],
            ['JAM-003', 'Jamón con notas de Cordero', 'JAMONES', SaleMode::WEIGHT, 56000, 3.500, 45, 0.06],

            // ── Tocinetas ────────────────────────────────────────────────────
            ['TOC-001', 'Tocineta Ahumada', 'TOCINETAS', SaleMode::WEIGHT, 35600, 1.000, 60, 0.07],

            // ── Pavos y navideños ────────────────────────────────────────────
            ['PAV-001', 'Pavo', 'PAVOS', SaleMode::WEIGHT, 54000, 5.000, 180, 0.00],
            ['PAV-002', 'Pavo Navideño', 'PAVOS', SaleMode::WEIGHT, 55000, 5.500, 180, 0.00],
            ['PAV-003', 'Galantina de Pavo', 'PAVOS', SaleMode::WEIGHT, 42000, 2.000, 45, 0.06],

            // ── Especiales ───────────────────────────────────────────────────
            ['ESP-001', 'Mini Pernil', 'ESPECIALES', SaleMode::WEIGHT, 58000, 2.500, 90, 0.00],
            ['ESP-002', 'Hamburguesas de Cerdo x5', 'ESPECIALES', SaleMode::WEIGHT, 36900, 0.600, 120, 0.00],
            ['ESP-003', 'Queso de Cabeza', 'ESPECIALES', SaleMode::UNIT, 19500, null, 20, 0.00],

            // ── Morcillas ────────────────────────────────────────────────────
            ['MOR-001', 'Morcillas Caseras', 'MORCILLAS', SaleMode::WEIGHT, 16400, 0.150, 15, 0.12],

            // ── Quesos ───────────────────────────────────────────────────────
            // Bloques: pieza entera de peso variable, se pesa al despachar.
            ['QUE-001', 'Queso Campesino', 'QUESOS', SaleMode::BLOCK, 18000, null, 20, 0.05],
            ['QUE-002', 'Cuajada', 'QUESOS', SaleMode::BLOCK, 16000, null, 12, 0.06],
        ];

        foreach ($products as [$sku, $name, $categoryCode, $saleMode, $price, $netWeight, $shelfLife, $shrinkage]) {
            Product::updateOrCreate(
                ['sku' => $sku],
                [
                    'categoryId' => $categories[$categoryCode],
                    'name' => $name,
                    'brand' => null,
                    'saleMode' => $saleMode->value,
                    'tracksWeight' => $saleMode->tracksWeight(),
                    'netWeightKg' => $netWeight,
                    'basePrice' => $price,
                    'priceIncludesTax' => true,
                    // Los cárnicos frescos y procesados básicos son excluidos de IVA
                    // en Colombia. El cliente confirma referencia por referencia.
                    'taxPercent' => 0,
                    'purchaseUnitId' => $units[$saleMode === SaleMode::WEIGHT ? 'KG' : 'UN'] ?? null,
                    'saleUnitId' => $units[$saleMode === SaleMode::WEIGHT ? 'KG' : 'UN'] ?? null,
                    'trackLots' => true,
                    'shelfLifeDays' => $shelfLife,
                    'expirationAlertDays' => 7,
                    'shrinkagePercentPerDay' => $shrinkage,
                    'sellable' => true,
                    'purchasable' => true,
                    'active' => true,
                ],
            );
        }
    }

    /** @return array<string, int> code → id */
    private function seedCategories(): array
    {
        $tree = [
            'SALSAMENTARIA' => ['Salsamentaria', null],
            'CHORIZOS' => ['Chorizos', 'SALSAMENTARIA'],
            'SALCHICHAS' => ['Salchichas y salchichón', 'SALSAMENTARIA'],
            'JAMONES' => ['Jamones', 'SALSAMENTARIA'],
            'TOCINETAS' => ['Tocinetas', 'SALSAMENTARIA'],
            'PAVOS' => ['Pavos y navideños', 'SALSAMENTARIA'],
            'MORCILLAS' => ['Morcillas', 'SALSAMENTARIA'],
            'ESPECIALES' => ['Especiales', 'SALSAMENTARIA'],
            'QUESOS' => ['Quesos', 'SALSAMENTARIA'],
        ];

        $ids = [];
        $order = 0;

        foreach ($tree as $code => [$name, $parentCode]) {
            $category = Category::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'parentId' => $parentCode ? $ids[$parentCode] : null,
                    'displayOrder' => $order++,
                    'active' => true,
                ],
            );

            $ids[$code] = $category->id;
        }

        return $ids;
    }
}
