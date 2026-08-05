<?php

namespace Tests\Feature;

use App\Enums\MovementType;
use App\Models\Lot;
use App\Models\Product;
use App\Models\Stock;
use App\Services\InventoryService;
use App\Services\StockReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * La prueba que DaliOrder no tiene: después de una secuencia arbitraria de
 * operaciones, las tres fuentes del inventario deben cuadrar EXACTAMENTE, tanto
 * en unidades como en kilos.
 */
class StockReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private InventoryService $inventory;

    private StockReconciliationService $reconciliation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inventory = app(InventoryService::class);
        $this->reconciliation = app(StockReconciliationService::class);
    }

    public function test_un_inventario_recien_creado_cuadra(): void
    {
        $product = Product::factory()->byWeight()->create();
        $this->inventory->receive($product, 20, 12.5, 300000);

        $this->assertTrue($this->reconciliation->findDiscrepancies()->isEmpty());
    }

    /**
     * Secuencia pseudoaleatoria pero DETERMINISTA (semilla fija) de recepciones,
     * ventas FIFO, despachos de lote concreto, mermas y ajustes sobre los tres
     * modos de venta. Al final las tres fuentes deben coincidir.
     */
    public function test_las_tres_fuentes_cuadran_tras_cientos_de_operaciones(): void
    {
        mt_srand(20260731);

        $products = [
            Product::factory()->byWeight()->create([
                'sku' => 'CHO-004', 'shelfLifeDays' => null,
            ]),
            Product::factory()->byUnit()->create([
                'sku' => 'ESP-003', 'shelfLifeDays' => null,
            ]),
            Product::factory()->fixedPack(0.5)->create([
                'sku' => 'SAL-003', 'shelfLifeDays' => null,
            ]),
        ];

        $outTypes = [MovementType::SALE, MovementType::WASTE, MovementType::RETURN_TO_SUPPLIER];
        $reference = 0;

        for ($i = 0; $i < 300; $i++) {
            $product = $products[mt_rand(0, 2)];

            switch (mt_rand(1, 5)) {
                case 1:
                case 2:
                    // Recepción: cantidades y costos variados.
                    $units = mt_rand(1, 40);
                    $kg = mt_rand(1000, 30000) / 1000;
                    $this->inventory->receive(
                        product: $product,
                        units: $units,
                        kg: $kg,
                        totalCost: mt_rand(50000, 900000),
                        expirationDate: mt_rand(0, 4) === 0 ? null : now()->addDays(mt_rand(1, 90)),
                    );
                    break;

                case 3:
                case 4:
                    // Salida FIFO por la cantidad conductora del producto.
                    $this->tryOut(fn () => $this->inventory->consumeFifo(
                        product: $product,
                        quantity: mt_rand(100, 8000) / 1000,
                        type: $outTypes[mt_rand(0, 2)],
                        referenceType: 'test',
                        referenceId: ++$reference,
                    ));
                    break;

                case 5:
                    // Operación sobre un lote concreto: despacho con peso real o ajuste.
                    $lot = Lot::query()
                        ->where('productId', $product->id)
                        ->withStock()
                        ->inRandomOrder()
                        ->first();

                    if ($lot === null) {
                        break;
                    }

                    if (mt_rand(0, 1) === 0) {
                        $this->tryOut(fn () => $this->inventory->consumeFromLot(
                            lot: $lot,
                            units: min((float) $lot->currentUnits, (float) mt_rand(0, 5)),
                            kg: min((float) $lot->currentKg, mt_rand(0, 3000) / 1000),
                            type: MovementType::SALE,
                            referenceType: 'test',
                            referenceId: ++$reference,
                        ));
                    } else {
                        $this->tryOut(fn () => $this->inventory->adjust(
                            lot: $lot,
                            unitsDelta: 0,
                            kgDelta: -min((float) $lot->currentKg, mt_rand(1, 500) / 1000),
                            reason: 'Ajuste de prueba',
                        ));
                    }
                    break;
            }
        }

        $discrepancies = $this->reconciliation->findDiscrepancies();

        $this->assertTrue(
            $discrepancies->isEmpty(),
            "Las tres fuentes divergen:\n" . $discrepancies->map->summary()->implode("\n"),
        );

        // Y la secuencia realmente movió inventario, no fue toda no-ops.
        $this->assertGreaterThan(50, \App\Models\StockMovement::count());
        $this->assertGreaterThan(0, Stock::sum('currentKg'));
    }

    public function test_detecta_un_descuadre_provocado_por_fuera_del_servicio(): void
    {
        $product = Product::factory()->byWeight()->create();
        $this->inventory->receive($product, 20, 12.5, 300000);

        // Alguien toca el saldo sin pasar por el kardex: exactamente el escenario
        // que la reconciliación existe para descubrir.
        Stock::query()
            ->where('productId', $product->id)
            ->first()
            ->forceFill(['currentKg' => 99])
            ->save();

        $discrepancies = $this->reconciliation->findDiscrepancies();

        $this->assertCount(1, $discrepancies);
        $this->assertSame(99.0, $discrepancies->first()->stockKg);
        $this->assertSame(12.5, $discrepancies->first()->lotKg);
        $this->assertSame(12.5, $discrepancies->first()->movementKg);
    }

    /** Ejecuta una operación ignorando los rechazos de negocio esperados (stock insuficiente). */
    private function tryOut(callable $operation): void
    {
        try {
            $operation();
        } catch (HttpException $e) {
            // 409 = sin stock suficiente; 422 = cantidad inválida. Ambos son
            // rechazos legítimos que no deben descuadrar nada.
            if (! in_array($e->getStatusCode(), [409, 422], true)) {
                throw $e;
            }
        }
    }
}
