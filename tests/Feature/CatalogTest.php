<?php

namespace Tests\Feature;

use App\Enums\SaleMode;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Product;
use App\Models\User;
use App\Models\Unit;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\CompanySeeder;
use Database\Seeders\UnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_seed_carga_el_catalogo_con_los_cuatro_modos_de_venta(): void
    {
        $this->seed(CompanySeeder::class);
        $this->seed(UnitSeeder::class);
        $this->seed(CatalogSeeder::class);

        $products = Product::all();

        $this->assertGreaterThanOrEqual(18, $products->count());

        foreach (SaleMode::cases() as $mode) {
            $this->assertTrue(
                $products->contains(fn (Product $p) => $p->saleMode === $mode),
                "El catálogo debe ejercitar el modo {$mode->value}.",
            );
        }

        // El queso campesino es un bloque: pieza de peso variable.
        $queso = $products->firstWhere('sku', 'QUE-001');
        $this->assertSame(SaleMode::BLOCK, $queso->saleMode);
        $this->assertTrue($queso->tracksWeight());
        $this->assertNull($queso->netWeightKg);

        // El chorizo santarrosano se vende por kg a $32.000.
        $chorizo = $products->firstWhere('sku', 'CHO-004');
        $this->assertSame(SaleMode::WEIGHT, $chorizo->saleMode);
        $this->assertSame('32000.00', $chorizo->basePrice);
        $this->assertTrue($chorizo->tracksWeight());

        // El queso de cabeza se vende por unidad y no lleva saldo en kg.
        $queso = $products->firstWhere('sku', 'ESP-003');
        $this->assertSame(SaleMode::UNIT, $queso->saleMode);
        $this->assertFalse($queso->tracksWeight());
        $this->assertNull($queso->netWeightKg);
    }

    public function test_el_seed_de_unidades_deja_una_base_por_naturaleza(): void
    {
        $this->seed(CompanySeeder::class);
        $this->seed(UnitSeeder::class);

        $bases = Unit::where('isBase', true)->pluck('code')->sort()->values()->all();

        $this->assertSame(['KG', 'L', 'UN'], $bases);
    }

    public function test_la_libra_del_seed_es_de_500_gramos(): void
    {
        $this->seed(CompanySeeder::class);
        $this->seed(UnitSeeder::class);

        // Libra comercial colombiana: 1 kg = 2 libras exactas.
        $libra = Unit::where('code', 'LB')->firstOrFail();

        $this->assertSame(0.5, (float) $libra->factorToBase);
    }

    public function test_la_tolerancia_de_peso_cae_a_la_del_negocio_cuando_el_producto_no_la_define(): void
    {
        Company::factory()->create(['defaultWeightTolerancePercent' => 8]);

        $sinTolerancia = Product::factory()->create(['weightTolerancePercent' => null]);
        $conTolerancia = Product::factory()->create(['weightTolerancePercent' => 3]);

        $this->assertSame(8.0, $sinTolerancia->effectiveWeightTolerancePercent());
        $this->assertSame(3.0, $conTolerancia->effectiveWeightTolerancePercent());
    }

    public function test_estima_el_peso_de_una_cantidad_de_piezas(): void
    {
        $chorizo = Product::factory()->byWeight()->create(['netWeightKg' => 0.1]);
        $porUnidad = Product::factory()->byUnit()->create();

        $this->assertSame(0.3, $chorizo->estimateKgForUnits(3));
        // Sin peso de referencia no se estima: devolver 0 sería mentir.
        $this->assertNull($porUnidad->estimateKgForUnits(3));
    }

    public function test_todos_los_usuarios_ven_el_mismo_catalogo(): void
    {
        Product::factory()->count(3)->create();

        // Sin multi-tenancy no hay scope global que filtre: cualquier usuario
        // autenticado ve el catálogo completo del negocio.
        $this->actingAs(User::factory()->role(UserRole::VENDEDOR)->create());
        $this->assertSame(3, Product::count());

        $this->actingAs(User::factory()->role(UserRole::EMPACADOR)->create());
        $this->assertSame(3, Product::count());
    }
}
