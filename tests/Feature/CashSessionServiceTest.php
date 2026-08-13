<?php

namespace Tests\Feature;

use App\Enums\CashSessionStatus;
use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use App\Models\CashSession;
use App\Models\Supplier;
use App\Models\User;
use App\Services\CashSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CashSessionServiceTest extends TestCase
{
    use RefreshDatabase;

    private CashSessionService $service;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CashSessionService::class);
        $this->user = User::factory()->create();
    }

    public function test_abrir_crea_el_cierre_del_dia_con_la_base(): void
    {
        $session = $this->service->openForDate('2026-08-10', 289000, $this->user->id);

        $this->assertSame(CashSessionStatus::OPEN, $session->status);
        $this->assertSame('289000.00', $session->baseAmount);
        $this->assertSame('2026-08-10', $session->businessDate->toDateString());
    }

    public function test_no_duplica_el_cierre_de_la_misma_fecha(): void
    {
        $first = $this->service->openForDate('2026-08-10', 289000, $this->user->id);
        $second = $this->service->openForDate('2026-08-10', 999999, $this->user->id);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, CashSession::count());
        // No pisa la base ya guardada del primer open.
        $this->assertSame('289000.00', $second->baseAmount);
    }

    public function test_recalcula_el_descuadre_con_el_ejemplo_real(): void
    {
        $session = $this->service->openForDate('2026-08-10', 289000, $this->user->id);

        $session = $this->service->saveDraft($session, [
            'salesCash' => 706400,
            // Arqueo físico que suma 1.301.400: 13×100.000 + 1×1.000 + 2×200.
            'denominations' => [
                ['denomination' => 100000, 'quantity' => 13],
                ['denomination' => 1000, 'quantity' => 1],
                ['denomination' => 200, 'quantity' => 2],
            ],
            'expenses' => [
                ['description' => 'Rosa Vargas', 'amount' => 296000, 'category' => ExpenseCategory::NOMINA->value, 'paymentMethod' => PaymentMethod::CASH->value],
            ],
        ]);

        $this->assertSame('1301400.00', $session->countedCashTotal);
        $this->assertSame('296000.00', $session->expensesTotal);
        // expectedCash = base(289.000) + salesCash(706.400) − egresos en efectivo(296.000) = 699.400
        $this->assertSame('699400.00', $session->expectedCash);
        // overShort = contado(1.301.400) − esperado(699.400) = 602.000
        $this->assertSame('602000.00', $session->overShort);
    }

    public function test_los_egresos_que_no_son_en_efectivo_no_afectan_lo_esperado(): void
    {
        $session = $this->service->openForDate('2026-08-11', 100000, $this->user->id);

        $session = $this->service->saveDraft($session, [
            'salesCash' => 200000,
            'expenses' => [
                ['description' => 'Transferencia arriendo', 'amount' => 50000, 'paymentMethod' => PaymentMethod::TRANSFER->value],
            ],
        ]);

        // 100.000 + 200.000 − 0 (el egreso fue por transferencia, no de caja) = 300.000
        $this->assertSame('300000.00', $session->expectedCash);
        $this->assertSame('50000.00', $session->expensesTotal);
    }

    public function test_las_cuentas_por_pagar_son_solo_informativas(): void
    {
        $session = $this->service->openForDate('2026-08-12', 100000, $this->user->id);
        $supplier = Supplier::factory()->create();

        $session = $this->service->saveDraft($session, [
            'payables' => [
                ['supplierId' => $supplier->id, 'concept' => 'Queso', 'totalAmount' => 5000000],
            ],
        ]);

        $this->assertSame('5000000.00', $session->payablesTotal);
        // No entra al esperado: expectedCash = base(100.000) + salesCash(0) − egresos en efectivo(0) = 100.000.
        $this->assertSame('100000.00', $session->expectedCash);
        // Sin arqueo físico registrado (countedCashTotal=0): overShort = 0 − 100.000 = −100.000.
        $this->assertSame('-100000.00', $session->overShort);
    }

    public function test_cerrar_bloquea_la_edicion(): void
    {
        $session = $this->service->openForDate('2026-08-13', 100000, $this->user->id);
        $this->service->close($session, $this->user->id);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('ya está cerrado');

        $this->service->saveDraft($session->refresh(), ['notes' => 'tarde']);
    }

    public function test_cerrar_un_cierre_ya_cerrado_falla(): void
    {
        $session = $this->service->openForDate('2026-08-14', 100000, $this->user->id);
        $this->service->close($session, $this->user->id);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('ya está cerrado');

        $this->service->close($session->refresh(), $this->user->id);
    }
}
