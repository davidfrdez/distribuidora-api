<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseType;
use App\Services\ColdChainService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ColdChainServiceTest extends TestCase
{
    use RefreshDatabase;

    private ColdChainService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ColdChainService::class);
    }

    private function refrigerator(): Warehouse
    {
        return Warehouse::factory()->create(['tempMin' => 0, 'tempMax' => 4]);
    }

    public function test_una_lectura_dentro_de_rango_no_genera_alerta(): void
    {
        $entry = $this->service->record($this->refrigerator(), 2.5);

        $this->assertFalse($entry->outOfRange);
        $this->assertSame('2.50', $entry->temperature);
        $this->assertSame('0.00', $entry->expectedMin);
        $this->assertSame('4.00', $entry->expectedMax);
    }

    public function test_marca_fuera_de_rango_por_encima_y_por_debajo(): void
    {
        $warehouse = $this->refrigerator();

        $this->assertTrue($this->service->record($warehouse, 9.1)->outOfRange);
        $this->assertTrue($this->service->record($warehouse, -3)->outOfRange);
    }

    public function test_hereda_el_rango_del_tipo_de_bodega_cuando_no_tiene_propio(): void
    {
        $type = WarehouseType::factory()->freezer()->create();
        $freezer = Warehouse::factory()->withoutOwnRange()->create(['warehouseTypeId' => $type->id]);

        // -15 está dentro del rango de congelación (-18 a -12) pero fuera del
        // rango de refrigeración: prueba que realmente heredó el del tipo.
        $ok = $this->service->record($freezer, -15);
        $this->assertFalse($ok->outOfRange);
        $this->assertSame('-18.00', $ok->expectedMin);

        $this->assertTrue($this->service->record($freezer, 2)->outOfRange);
    }

    public function test_una_bodega_sin_rango_configurado_nunca_esta_fuera_de_rango(): void
    {
        $dry = Warehouse::factory()->withoutOwnRange()->create();

        $this->assertFalse($this->service->record($dry, 25)->outOfRange);
    }

    public function test_guarda_quien_registro_la_lectura(): void
    {
        $user = User::factory()->create();

        $entry = $this->service->record($this->refrigerator(), 3, $user->id, 'SENSOR');

        $this->assertSame($user->id, $entry->recordedById);
        $this->assertSame('SENSOR', $entry->source);
    }

    public function test_lista_solo_las_desviaciones_de_la_ventana_pedida(): void
    {
        $warehouse = $this->refrigerator();

        $this->service->record($warehouse, 12, recordedAt: now()->subDays(5));   // vieja
        $this->service->record($warehouse, 2, recordedAt: now()->subHours(3));   // normal
        $this->service->record($warehouse, 11, recordedAt: now()->subHours(2));  // desviación

        $deviations = $this->service->deviations($warehouse, now()->subDay());

        $this->assertCount(1, $deviations);
        $this->assertSame('11.00', $deviations->first()->temperature);
    }

    public function test_detecta_ruptura_sostenida_y_no_una_desviacion_aislada(): void
    {
        $sostenida = $this->refrigerator();
        // Puerta abierta durante dos horas: tres lecturas malas seguidas.
        $this->service->record($sostenida, 11, recordedAt: now()->subMinutes(150));
        $this->service->record($sostenida, 12, recordedAt: now()->subMinutes(90));
        $this->service->record($sostenida, 10, recordedAt: now()->subMinutes(30));

        $this->assertTrue($this->service->hasSustainedBreach($sostenida, now()->subDay()));

        $aislada = $this->refrigerator();
        // Un pico al recibir mercancía, y la bodega recupera temperatura.
        $this->service->record($aislada, 11, recordedAt: now()->subMinutes(150));
        $this->service->record($aislada, 2, recordedAt: now()->subMinutes(90));
        $this->service->record($aislada, 11, recordedAt: now()->subMinutes(30));

        $this->assertFalse($this->service->hasSustainedBreach($aislada, now()->subDay()));
    }

    public function test_la_bodega_de_cuarentena_no_puede_despachar(): void
    {
        $quarantine = Warehouse::factory()->quarantine()->create();

        $this->assertFalse($quarantine->canDispatch());
        $this->assertTrue($this->refrigerator()->canDispatch());
    }

}
