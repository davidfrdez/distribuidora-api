<?php

namespace Tests\Feature;

use App\Enums\CashMovementType;
use App\Enums\CashSessionStatus;
use App\Models\CashSession;
use App\Models\User;
use App\Services\CashService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CashServiceTest extends TestCase
{
    use RefreshDatabase;

    private CashService $service;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CashService::class);
        $this->user = User::factory()->create();
    }

    public function test_abrir_crea_un_turno_con_la_base(): void
    {
        $session = $this->service->open($this->user->id, 100000);

        $this->assertSame(CashSessionStatus::OPEN, $session->status);
        $this->assertSame('100000.00', $session->openingAmount);
        $this->assertNotNull($this->service->currentOpen());
    }

    public function test_no_se_puede_abrir_una_segunda_caja(): void
    {
        $this->service->open($this->user->id, 100000);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Ya hay una caja abierta');

        $this->service->open($this->user->id, 50000);
    }

    public function test_el_esperado_suma_ingresos_y_resta_egresos(): void
    {
        $session = $this->service->open($this->user->id, 100000);

        $this->service->addMovement($session, CashMovementType::INCOME, 250000, 'Ventas de la mañana');
        $this->service->addMovement($session, CashMovementType::EXPENSE, 40000, 'Pago mensajero');
        $this->service->addMovement($session, CashMovementType::WITHDRAWAL, 100000, 'Retiro al banco');

        // 100.000 + 250.000 − 40.000 − 100.000 = 210.000
        $this->assertSame(210000.0, $this->service->expectedCash($session));
    }

    public function test_cerrar_calcula_el_descuadre(): void
    {
        $session = $this->service->open($this->user->id, 100000);
        $this->service->addMovement($session, CashMovementType::INCOME, 200000, 'Ventas');

        // Esperado 300.000; se cuentan 295.000 → faltan 5.000.
        $closed = $this->service->close($session, 295000, $this->user->id);

        $this->assertSame(CashSessionStatus::CLOSED, $closed->status);
        $this->assertSame('300000.00', $closed->closingExpected);
        $this->assertSame('295000.00', $closed->closingCounted);
        $this->assertSame('-5000.00', $closed->difference);
        $this->assertNotNull($closed->closedAt);
    }

    public function test_una_caja_cerrada_no_admite_movimientos(): void
    {
        $session = $this->service->open($this->user->id, 100000);
        $this->service->close($session, 100000, $this->user->id);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('cerrada');

        $this->service->addMovement($session->refresh(), CashMovementType::INCOME, 1000, 'tardío');
    }

    public function test_cerrar_una_caja_ya_cerrada_falla(): void
    {
        $session = $this->service->open($this->user->id, 100000);
        $this->service->close($session, 100000, $this->user->id);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('ya está cerrada');

        $this->service->close($session->refresh(), 100000, $this->user->id);
    }

    public function test_tras_cerrar_se_puede_abrir_otra(): void
    {
        $first = $this->service->open($this->user->id, 100000);
        $this->service->close($first, 100000, $this->user->id);

        $second = $this->service->open($this->user->id, 80000);

        $this->assertSame(CashSessionStatus::OPEN, $second->status);
        $this->assertSame($second->id, $this->service->currentOpen()?->id);
        $this->assertSame(2, CashSession::count());
    }
}
