<?php

namespace Tests\Feature;

use App\Enums\PayableStatus;
use App\Enums\PaymentMethod;
use App\Models\Payable;
use App\Models\Supplier;
use App\Services\PayableService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PayableServiceTest extends TestCase
{
    use RefreshDatabase;

    private PayableService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PayableService::class);
    }

    private function payable(float $total = 500000): Payable
    {
        return Payable::factory()->create([
            'supplierId' => Supplier::factory(),
            'totalAmount' => $total,
            'paidAmount' => 0,
            'status' => PayableStatus::PENDING->value,
        ]);
    }

    public function test_un_pago_parcial_deja_la_cuenta_parcial(): void
    {
        $payable = $this->payable(500000);

        $this->service->pay($payable, 200000, PaymentMethod::TRANSFER);

        $payable->refresh();
        $this->assertSame(PayableStatus::PARTIAL, $payable->status);
        $this->assertSame('200000.00', $payable->paidAmount);
        $this->assertSame(300000.0, $payable->balance());
    }

    public function test_un_pago_que_salda_la_marca_pagada(): void
    {
        $payable = $this->payable(500000);

        $this->service->pay($payable, 200000, PaymentMethod::CASH);
        $this->service->pay($payable, 300000, PaymentMethod::NEQUI);

        $payable->refresh();
        $this->assertSame(PayableStatus::PAID, $payable->status);
        $this->assertSame(0.0, $payable->balance());
        $this->assertCount(2, $payable->payments);
    }

    public function test_no_se_puede_pagar_mas_que_el_saldo(): void
    {
        $payable = $this->payable(500000);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('supera el saldo pendiente');

        $this->service->pay($payable, 500001, PaymentMethod::CASH);
    }

    public function test_una_cuenta_pagada_no_admite_mas_pagos(): void
    {
        $payable = $this->payable(100000);
        $this->service->pay($payable, 100000, PaymentMethod::CASH);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('no admite más pagos');

        $this->service->pay($payable->refresh(), 1000, PaymentMethod::CASH);
    }

    public function test_anular_una_cuenta_la_saca_de_lo_que_se_debe(): void
    {
        $payable = $this->payable(100000);

        $this->service->void($payable, 'Factura duplicada');

        $this->assertSame(PayableStatus::VOID, $payable->refresh()->status);
        $this->assertSame(0, Payable::query()->open()->count());
    }

    public function test_el_resumen_calcula_lo_que_se_debe_y_lo_que_vence(): void
    {
        $today = Carbon::today();

        // Vencida ayer.
        Payable::factory()->create(['totalAmount' => 100000, 'status' => PayableStatus::PENDING->value])
            ->update(['dueDate' => $today->copy()->subDay()->toDateString()]);
        // Vence en 3 días (esta semana).
        Payable::factory()->create(['totalAmount' => 200000, 'status' => PayableStatus::PENDING->value])
            ->update(['dueDate' => $today->copy()->addDays(3)->toDateString()]);
        // Vence en 30 días (abierta, pero no esta semana).
        Payable::factory()->create(['totalAmount' => 300000, 'status' => PayableStatus::PENDING->value])
            ->update(['dueDate' => $today->copy()->addDays(30)->toDateString()]);
        // Pagada: no cuenta para lo que se debe.
        Payable::factory()->create([
            'totalAmount' => 50000, 'paidAmount' => 50000, 'status' => PayableStatus::PAID->value,
        ]);

        $summary = $this->service->summary($today);

        $this->assertSame(600000.0, $summary['totalOwed']);
        $this->assertSame(3, $summary['openCount']);
        $this->assertSame(1, $summary['overdueCount']);
        $this->assertSame(100000.0, $summary['overdueAmount']);
        $this->assertSame(1, $summary['dueThisWeekCount']);
        $this->assertSame(200000.0, $summary['dueThisWeekAmount']);
    }
}
