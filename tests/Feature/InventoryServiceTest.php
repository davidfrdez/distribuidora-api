<?php

namespace Tests\Feature;

use App\Enums\LotStatus;
use App\Enums\MovementType;
use App\Enums\ReservationStatus;
use App\Models\Lot;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class InventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private InventoryService $service;

    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(InventoryService::class);
        $this->warehouse = Warehouse::factory()->create();
    }

    private function product(string $state = 'byWeight', array $attributes = []): Product
    {
        return Product::factory()->{$state}()->create($attributes);
    }

    private function stockOf(Product $product, ?Warehouse $warehouse = null): Stock
    {
        return Stock::query()
            ->where('productId', $product->id)
            ->where('warehouseId', ($warehouse ?? $this->warehouse)->id)
            ->firstOrFail();
    }

    // ── Recepción ────────────────────────────────────────────────────────────

    public function test_recibe_mercancia_creando_lote_movimiento_y_saldo(): void
    {
        $chorizo = $this->product('byWeight', ['name' => 'Chorizo Santarrosano']);
        $proveedor = Supplier::factory()->create();

        // Una canastilla: 20 chorizos, 12,5 kg, $300.000.
        $lot = $this->service->receive(
            product: $chorizo,
            warehouse: $this->warehouse,
            units: 20,
            kg: 12.5,
            totalCost: 300000,
            supplier: $proveedor,
            supplierLotCode: 'FAB-4471',
        );

        $this->assertSame('20.0000', $lot->currentUnits);
        $this->assertSame('12.5000', $lot->currentKg);
        $this->assertSame('24000.0000', $lot->costPerKg);   // 300.000 / 12,5
        $this->assertSame('15000.0000', $lot->costPerUnit); // 300.000 / 20
        $this->assertSame('FAB-4471', $lot->supplierLotCode);
        $this->assertSame($proveedor->id, $lot->supplierId);
        $this->assertStringStartsWith('LOT-', $lot->code);

        $stock = $this->stockOf($chorizo);
        $this->assertSame('20.0000', $stock->currentUnits);
        $this->assertSame('12.5000', $stock->currentKg);
        $this->assertSame('12.5000', $stock->availableKg);

        $movement = StockMovement::firstOrFail();
        $this->assertSame(MovementType::PURCHASE, $movement->type);
        $this->assertSame($lot->id, $movement->lotId);
        $this->assertSame('0.0000', $movement->kgBefore);
        $this->assertSame('12.5000', $movement->kgAfter);
    }

    public function test_deriva_el_vencimiento_de_la_vida_util(): void
    {
        $chorizo = $this->product('byWeight', ['shelfLifeDays' => 30]);

        $lot = $this->service->receive($chorizo, $this->warehouse, 10, 5, 100000);

        $this->assertSame(now()->addDays(30)->toDateString(), $lot->expirationDate->toDateString());
    }

    public function test_el_vencimiento_se_cuenta_desde_la_fabricacion_si_se_informa(): void
    {
        $chorizo = $this->product('byWeight', ['shelfLifeDays' => 30]);
        $fabricacion = now()->subDays(10);

        $lot = $this->service->receive(
            $chorizo, $this->warehouse, 10, 5, 100000,
            manufacturingDate: $fabricacion,
        );

        $this->assertSame($fabricacion->copy()->addDays(30)->toDateString(), $lot->expirationDate->toDateString());
    }

    public function test_un_paquete_de_peso_fijo_deriva_el_peso_de_las_unidades(): void
    {
        $paquete = $this->product('fixedPack', ['netWeightKg' => 0.5]);

        // Aunque se pase un peso distinto, manda netWeightKg × unidades.
        $lot = $this->service->receive($paquete, $this->warehouse, 24, 999, 348000);

        $this->assertSame('24.0000', $lot->currentUnits);
        $this->assertSame('12.0000', $lot->currentKg);
    }

    public function test_un_producto_por_unidad_no_lleva_saldo_en_kg(): void
    {
        $queso = $this->product('byUnit');

        $lot = $this->service->receive($queso, $this->warehouse, 12, 8, 180000);

        $this->assertSame('0.0000', $lot->currentKg);
        $this->assertSame('15000.0000', $lot->costPerUnit);
        $this->assertSame('0.0000', $this->stockOf($queso)->currentKg);
    }

    public function test_recibir_peso_variable_sin_kilos_falla(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('se vende por peso');

        $this->service->receive($this->product('byWeight'), $this->warehouse, 10, 0, 100000);
    }

    public function test_recibir_por_unidad_sin_unidades_falla(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('se maneja por unidad');

        $this->service->receive($this->product('byUnit'), $this->warehouse, 0, 5, 100000);
    }

    public function test_el_costo_promedio_se_pondera_entre_recepciones(): void
    {
        $chorizo = $this->product('byWeight', [
            'averageCostPerKg' => 0,
            'averageCostPerUnit' => 0,
        ]);

        // 10 kg a $20.000/kg, después 10 kg a $30.000/kg → promedio $25.000.
        $this->service->receive($chorizo, $this->warehouse, 10, 10, 200000);
        $this->service->receive($chorizo, $this->warehouse, 10, 10, 300000);

        $chorizo->refresh();
        $this->assertSame('25000.0000', $chorizo->averageCostPerKg);
        $this->assertSame('30000.0000', $chorizo->lastCostPerKg);
    }

    // ── Consumo FIFO ─────────────────────────────────────────────────────────

    public function test_el_fifo_saca_primero_el_lote_que_vence_antes(): void
    {
        $chorizo = $this->product('byWeight', ['shelfLifeDays' => null]);

        $tarde = $this->service->receive($chorizo, $this->warehouse, 10, 10, 200000, expirationDate: now()->addDays(30));
        $pronto = $this->service->receive($chorizo, $this->warehouse, 10, 10, 200000, expirationDate: now()->addDays(5));

        $lines = $this->service->consumeFifo(
            $chorizo, $this->warehouse, 4, MovementType::SALE, 'order', 1,
        );

        $this->assertCount(1, $lines);
        $this->assertSame($pronto->id, $lines[0]->lot->id);
        $this->assertSame(4.0, $lines[0]->kg);
        $this->assertSame('6.0000', $pronto->refresh()->currentKg);
        $this->assertSame('10.0000', $tarde->refresh()->currentKg);
    }

    public function test_los_lotes_sin_vencimiento_salen_al_final(): void
    {
        $chorizo = $this->product('byWeight', ['shelfLifeDays' => null]);

        $sinFecha = $this->service->receive($chorizo, $this->warehouse, 10, 10, 200000);
        $conFecha = $this->service->receive($chorizo, $this->warehouse, 10, 10, 200000, expirationDate: now()->addDays(60));

        $lines = $this->service->consumeFifo($chorizo, $this->warehouse, 3, MovementType::SALE, 'order', 1);

        $this->assertSame($conFecha->id, $lines[0]->lot->id);
        $this->assertSame('10.0000', $sinFecha->refresh()->currentKg);
    }

    public function test_el_fifo_reparte_la_salida_entre_varios_lotes(): void
    {
        $chorizo = $this->product('byWeight', ['shelfLifeDays' => null]);

        $primero = $this->service->receive($chorizo, $this->warehouse, 20, 10, 200000, expirationDate: now()->addDays(5));
        $segundo = $this->service->receive($chorizo, $this->warehouse, 20, 10, 300000, expirationDate: now()->addDays(20));

        $lines = $this->service->consumeFifo($chorizo, $this->warehouse, 14, MovementType::SALE, 'order', 1);

        $this->assertCount(2, $lines);
        $this->assertSame(10.0, $lines[0]->kg);   // agota el primero
        $this->assertSame(4.0, $lines[1]->kg);

        // Al agotar el conductor del primer lote se arrastran todas sus piezas.
        $this->assertSame(20.0, $lines[0]->units);
        $this->assertSame(LotStatus::DEPLETED, $primero->refresh()->status);

        // Del segundo salen las piezas proporcionales: 4 de 10 kg → 8 de 20 piezas.
        $this->assertSame(8.0, $lines[1]->units);
        $this->assertSame('6.0000', $segundo->refresh()->currentKg);
        $this->assertSame('12.0000', $segundo->currentUnits);
    }

    public function test_el_costo_de_la_salida_es_el_del_lote_no_el_promedio(): void
    {
        $chorizo = $this->product('byWeight', ['shelfLifeDays' => null]);

        // Lote barato que vence primero, lote caro después.
        $this->service->receive($chorizo, $this->warehouse, 10, 10, 200000, expirationDate: now()->addDays(5));
        $this->service->receive($chorizo, $this->warehouse, 10, 10, 400000, expirationDate: now()->addDays(30));

        $lines = $this->service->consumeFifo($chorizo, $this->warehouse, 5, MovementType::SALE, 'order', 1);

        // 5 kg del lote barato a $20.000/kg = $100.000, no el promedio de $30.000.
        $this->assertSame(100000.0, $lines[0]->cost);
    }

    public function test_no_deja_consumir_mas_de_lo_que_hay(): void
    {
        $chorizo = $this->product('byWeight', ['name' => 'Chorizo Ahumado']);
        $this->service->receive($chorizo, $this->warehouse, 10, 5, 100000);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Stock insuficiente');

        $this->service->consumeFifo($chorizo, $this->warehouse, 6, MovementType::SALE, 'order', 1);
    }

    public function test_ignora_lotes_vencidos_o_en_cuarentena(): void
    {
        $chorizo = $this->product('byWeight', ['shelfLifeDays' => null]);

        $vencido = $this->service->receive($chorizo, $this->warehouse, 10, 10, 200000, expirationDate: now()->addDay());
        $vencido->forceFill(['status' => LotStatus::EXPIRED->value])->save();

        $retenido = $this->service->receive($chorizo, $this->warehouse, 10, 10, 200000, expirationDate: now()->addDays(2));
        $retenido->forceFill(['status' => LotStatus::QUARANTINE->value])->save();

        $bueno = $this->service->receive($chorizo, $this->warehouse, 10, 10, 200000, expirationDate: now()->addDays(30));

        $lines = $this->service->consumeFifo($chorizo, $this->warehouse, 10, MovementType::SALE, 'order', 1);

        $this->assertCount(1, $lines);
        $this->assertSame($bueno->id, $lines[0]->lot->id);

        // Y no puede consumir más, aunque haya 30 kg físicos en la bodega.
        $this->expectException(HttpException::class);
        $this->service->consumeFifo($chorizo, $this->warehouse, 1, MovementType::SALE, 'order', 2);
    }

    public function test_una_entrada_no_puede_usarse_como_consumo(): void
    {
        $chorizo = $this->product();

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('no es una salida de inventario');

        $this->service->consumeFifo($chorizo, $this->warehouse, 1, MovementType::PURCHASE, 'order', 1);
    }

    // ── Consumo de un lote concreto ──────────────────────────────────────────

    public function test_descuenta_de_un_lote_elegido_con_el_peso_real(): void
    {
        $chorizo = $this->product('byWeight');
        $lot = $this->service->receive($chorizo, $this->warehouse, 20, 12.5, 300000);

        // El empacador pesó 2,140 kg de 3 chorizos: es el peso real, no una regla de tres.
        $line = $this->service->consumeFromLot(
            $lot, 3, 2.14, MovementType::SALE, 'order', 1,
        );

        $this->assertSame(3.0, $line->units);
        $this->assertSame(2.14, $line->kg);
        $this->assertSame('17.0000', $lot->refresh()->currentUnits);
        $this->assertSame('10.3600', $lot->currentKg);
        $this->assertSame('10.3600', $this->stockOf($chorizo)->currentKg);
    }

    public function test_no_deja_descontar_mas_de_lo_que_tiene_el_lote(): void
    {
        $chorizo = $this->product('byWeight');
        $lot = $this->service->receive($chorizo, $this->warehouse, 20, 12.5, 300000);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('kg; se pidieron');

        $this->service->consumeFromLot($lot, 1, 13, MovementType::SALE, 'order', 1);
    }

    public function test_un_lote_en_cuarentena_no_despacha(): void
    {
        $chorizo = $this->product('byWeight');
        $lot = $this->service->receive($chorizo, $this->warehouse, 20, 12.5, 300000);
        $lot->forceFill(['status' => LotStatus::QUARANTINE->value])->save();

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('no puede despachar');

        $this->service->consumeFromLot($lot, 1, 1, MovementType::SALE, 'order', 1);
    }

    // ── Inmutabilidad del kardex ─────────────────────────────────────────────

    public function test_el_kardex_no_se_puede_editar(): void
    {
        $chorizo = $this->product('byWeight');
        $this->service->receive($chorizo, $this->warehouse, 10, 5, 100000);

        $movement = StockMovement::firstOrFail();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('inmutable');

        $movement->update(['kg' => 999]);
    }

    public function test_el_kardex_no_se_puede_borrar(): void
    {
        $chorizo = $this->product('byWeight');
        $this->service->receive($chorizo, $this->warehouse, 10, 5, 100000);

        $movement = StockMovement::firstOrFail();

        $this->expectException(\LogicException::class);
        $movement->delete();
    }

    // ── Reservas ─────────────────────────────────────────────────────────────

    public function test_una_reserva_baja_el_disponible_sin_tocar_el_saldo(): void
    {
        $chorizo = $this->product('byWeight');
        $this->service->receive($chorizo, $this->warehouse, 20, 12.5, 300000);

        $this->service->reserve($chorizo, $this->warehouse, 5, 3, 'order', 77);

        $stock = $this->stockOf($chorizo);
        $this->assertSame('12.5000', $stock->currentKg);   // el stock sigue ahí
        $this->assertSame('3.0000', $stock->reservedKg);
        $this->assertSame('9.5000', $stock->availableKg);  // pero no está disponible
    }

    public function test_no_se_puede_reservar_mas_de_lo_disponible(): void
    {
        $chorizo = $this->product('byWeight', ['name' => 'Tocineta Ahumada']);
        $this->service->receive($chorizo, $this->warehouse, 20, 12.5, 300000);
        $this->service->reserve($chorizo, $this->warehouse, 5, 10, 'order', 1);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('kg disponibles');

        $this->service->reserve($chorizo, $this->warehouse, 1, 5, 'order', 2);
    }

    public function test_liberar_una_reserva_devuelve_el_disponible(): void
    {
        $chorizo = $this->product('byWeight');
        $this->service->receive($chorizo, $this->warehouse, 20, 12.5, 300000);
        $reserva = $this->service->reserve($chorizo, $this->warehouse, 5, 3, 'order', 1);

        $this->service->releaseReservation($reserva);

        $stock = $this->stockOf($chorizo);
        $this->assertSame('0.0000', $stock->reservedKg);
        $this->assertSame('12.5000', $stock->availableKg);
        $this->assertSame(ReservationStatus::RELEASED, $reserva->refresh()->status);
    }

    public function test_liberar_dos_veces_no_devuelve_el_stock_dos_veces(): void
    {
        $chorizo = $this->product('byWeight');
        $this->service->receive($chorizo, $this->warehouse, 20, 12.5, 300000);
        $reserva = $this->service->reserve($chorizo, $this->warehouse, 5, 3, 'order', 1);

        $this->service->releaseReservation($reserva);
        $this->service->releaseReservation($reserva);

        $this->assertSame('0.0000', $this->stockOf($chorizo)->reservedKg);
    }

    public function test_las_reservas_vencidas_se_liberan_solas(): void
    {
        $chorizo = $this->product('byWeight');
        $this->service->receive($chorizo, $this->warehouse, 20, 12.5, 300000);

        $vencida = $this->service->reserve(
            $chorizo, $this->warehouse, 5, 3, 'order', 1,
            expiresAt: now()->subHour(),
        );
        $vigente = $this->service->reserve(
            $chorizo, $this->warehouse, 2, 1, 'order', 2,
            expiresAt: now()->addHour(),
        );

        $liberadas = $this->service->expireStaleReservations();

        $this->assertSame(1, $liberadas);
        $this->assertSame(ReservationStatus::EXPIRED, $vencida->refresh()->status);
        $this->assertSame(ReservationStatus::ACTIVE, $vigente->refresh()->status);
        $this->assertSame('1.0000', $this->stockOf($chorizo)->reservedKg);
    }

    // ── Ajustes, traslados y anulación ───────────────────────────────────────

    public function test_un_ajuste_negativo_descuenta_del_lote(): void
    {
        $chorizo = $this->product('byWeight');
        $lot = $this->service->receive($chorizo, $this->warehouse, 20, 12.5, 300000);

        $this->service->adjust($lot, 0, -0.5, 'Conteo físico: faltaban 500 g');

        $this->assertSame('12.0000', $lot->refresh()->currentKg);
        $this->assertSame('12.0000', $this->stockOf($chorizo)->currentKg);
    }

    public function test_un_ajuste_no_puede_sumar_en_un_saldo_y_restar_en_el_otro(): void
    {
        $chorizo = $this->product('byWeight');
        $lot = $this->service->receive($chorizo, $this->warehouse, 20, 12.5, 300000);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Registra dos ajustes');

        $this->service->adjust($lot, 2, -1, 'Mezcla inválida');
    }

    public function test_un_ajuste_no_puede_dejar_el_lote_en_negativo(): void
    {
        $chorizo = $this->product('byWeight');
        $lot = $this->service->receive($chorizo, $this->warehouse, 20, 12.5, 300000);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('en negativo');

        $this->service->adjust($lot, 0, -20, 'Imposible');
    }

    public function test_el_traslado_crea_un_lote_en_destino_conservando_la_trazabilidad(): void
    {
        $chorizo = $this->product('byWeight', ['shelfLifeDays' => null]);
        $proveedor = Supplier::factory()->create();
        $congelador = Warehouse::factory()->create();

        $origen = $this->service->receive(
            $chorizo, $this->warehouse, 20, 12.5, 300000,
            supplier: $proveedor,
            supplierLotCode: 'FAB-9001',
            expirationDate: now()->addDays(45),
        );

        $result = $this->service->transfer($origen, $congelador, 8, 5);

        $destino = $result['lot'];
        $this->assertSame($congelador->id, $destino->warehouseId);
        $this->assertSame('FAB-9001', $destino->supplierLotCode);
        $this->assertSame($proveedor->id, $destino->supplierId);
        $this->assertSame($origen->expirationDate->toDateString(), $destino->expirationDate->toDateString());
        $this->assertSame('5.0000', $destino->currentKg);

        $this->assertSame('7.5000', $origen->refresh()->currentKg);
        $this->assertSame('7.5000', $this->stockOf($chorizo)->currentKg);
        $this->assertSame('5.0000', $this->stockOf($chorizo, $congelador)->currentKg);
    }

    public function test_no_se_puede_trasladar_a_la_misma_bodega(): void
    {
        $chorizo = $this->product('byWeight');
        $lot = $this->service->receive($chorizo, $this->warehouse, 20, 12.5, 300000);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('la misma bodega');

        $this->service->transfer($lot, $this->warehouse, 1, 1);
    }

    public function test_anular_un_lote_saca_todo_su_saldo(): void
    {
        $chorizo = $this->product('byWeight');
        $lot = $this->service->receive($chorizo, $this->warehouse, 20, 12.5, 300000);

        $this->service->voidLot($lot, 'Se recibió el producto equivocado');

        $this->assertSame(LotStatus::VOID, $lot->refresh()->status);
        $this->assertSame('0.0000', $lot->currentKg);
        $this->assertSame('0.0000', $lot->currentUnits);
        $this->assertSame('0.0000', $this->stockOf($chorizo)->currentKg);
    }

}
