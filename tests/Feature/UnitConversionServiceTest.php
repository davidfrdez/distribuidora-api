<?php

namespace Tests\Feature;

use App\Enums\UnitKind;
use App\Models\Product;
use App\Models\Unit;
use App\Models\UnitConversion;
use App\Services\UnitConversionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class UnitConversionServiceTest extends TestCase
{
    use RefreshDatabase;

    private UnitConversionService $service;
    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(UnitConversionService::class);
    }

    private function unit(string $code, UnitKind $kind, float $factorToBase, bool $isBase = false): Unit
    {
        return Unit::factory()->create([
            'code' => $code,
            'kind' => $kind->value,
            'factorToBase' => $factorToBase,
            'isBase' => $isBase,
        ]);
    }

    public function test_convierte_entre_unidades_de_peso_sin_configuracion(): void
    {
        $kg = $this->unit('KG', UnitKind::WEIGHT, 1, true);
        $g = $this->unit('G', UnitKind::WEIGHT, 0.001);
        $arroba = $this->unit('ARR', UnitKind::WEIGHT, 12.5);

        $this->assertSame(2500.0, $this->service->convert(2.5, $kg, $g));
        $this->assertSame(12.5, $this->service->convert(1, $arroba, $kg));
        $this->assertEqualsWithDelta(0.2, $this->service->convert(2.5, $kg, $arroba), 0.0001);
    }

    public function test_convertir_a_la_misma_unidad_no_altera_la_cantidad(): void
    {
        $kg = $this->unit('KG', UnitKind::WEIGHT, 1, true);

        $this->assertSame(2.14, $this->service->convert(2.14, $kg, $kg));
    }

    public function test_canastilla_a_kg_usa_la_conversion_del_producto(): void
    {
        $kg = $this->unit('KG', UnitKind::WEIGHT, 1, true);
        $canastilla = $this->unit('CAN', UnitKind::COUNT, 1);

        $chorizo = Product::factory()->create(['sku' => 'CHO-004']);
        $morcilla = Product::factory()->create(['sku' => 'MOR-001']);

        // La misma canastilla pesa distinto según el producto.
        UnitConversion::factory()->create([
            'productId' => $chorizo->id,
            'fromUnitId' => $canastilla->id,
            'toUnitId' => $kg->id,
            'factor' => 12.5,
        ]);
        UnitConversion::factory()->create([
            'productId' => $morcilla->id,
            'fromUnitId' => $canastilla->id,
            'toUnitId' => $kg->id,
            'factor' => 8,
        ]);

        $this->assertSame(25.0, $this->service->convert(2, $canastilla, $kg, $chorizo));
        $this->assertSame(16.0, $this->service->convert(2, $canastilla, $kg, $morcilla));
    }

    public function test_usa_la_conversion_en_sentido_inverso_si_solo_existe_esa(): void
    {
        $kg = $this->unit('KG', UnitKind::WEIGHT, 1, true);
        $canastilla = $this->unit('CAN', UnitKind::COUNT, 1);
        $producto = Product::factory()->create();

        UnitConversion::factory()->create([
            'productId' => $producto->id,
            'fromUnitId' => $canastilla->id,
            'toUnitId' => $kg->id,
            'factor' => 12.5,
        ]);

        // Pide kg → canastilla, que no está definido: debe invertir el factor.
        $this->assertSame(2.0, $this->service->convert(25, $kg, $canastilla, $producto));
    }

    public function test_la_conversion_del_producto_gana_sobre_la_generica(): void
    {
        $kg = $this->unit('KG', UnitKind::WEIGHT, 1, true);
        $caja = $this->unit('CAJ', UnitKind::COUNT, 1);
        $producto = Product::factory()->create();

        UnitConversion::factory()->create([
            'productId' => null,
            'fromUnitId' => $caja->id,
            'toUnitId' => $kg->id,
            'factor' => 10,
        ]);
        UnitConversion::factory()->create([
            'productId' => $producto->id,
            'fromUnitId' => $caja->id,
            'toUnitId' => $kg->id,
            'factor' => 6,
        ]);

        $this->assertSame(6.0, $this->service->convert(1, $caja, $kg, $producto));
        // Sin producto sólo aplica la genérica.
        $this->assertSame(10.0, $this->service->convert(1, $caja, $kg));
    }

    public function test_falla_en_vez_de_adivinar_cuando_no_hay_conversion(): void
    {
        $kg = $this->unit('KG', UnitKind::WEIGHT, 1, true);
        $canastilla = $this->unit('CAN', UnitKind::COUNT, 1);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('No hay conversión definida de CAN a KG');

        $this->service->convert(1, $canastilla, $kg);
    }

    public function test_to_base_normaliza_a_la_unidad_base(): void
    {
        $arroba = $this->unit('ARR', UnitKind::WEIGHT, 12.5);

        $this->assertSame(25.0, $this->service->toBase(2, $arroba));
    }

}
