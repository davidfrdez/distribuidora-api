<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla `cash_session` — CIERRE DE CAJA DIARIO (arqueo), indexado por
 * `businessDate` (único: un cierre por día). Replica la hoja de cierre en
 * papel: base del día, ventas por forma de pago, arqueo de efectivo por
 * denominación (`cash_denomination`), egresos (`expense.cashSessionId`) y
 * cuentas por pagar (`payable.cashSessionId`) del día.
 *
 * No es un turno por evento (abrir→movimientos→cerrar): es un documento por
 * `businessDate` que se va completando en borrador (`OPEN`) hasta cerrarlo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_session', function (Blueprint $table) {
            $table->id();

            $table->date('businessDate');

            $table->decimal('baseAmount', 14, 2)->default(0);       // base del día (fondo inicial)

            // Ventas por forma de pago (digitadas a mano por ahora).
            $table->decimal('salesCash', 14, 2)->default(0);
            $table->decimal('salesBank', 14, 2)->default(0);
            $table->decimal('salesNequi', 14, 2)->default(0);
            $table->decimal('reportedSalesTotal', 14, 2)->default(0); // total venta del día

            $table->string('zNumber', 30)->nullable();              // nº de la tirilla Z
            $table->unsignedInteger('zInvoiceCount')->nullable();    // nº de facturas de la Z

            $table->decimal('countedCashTotal', 14, 2)->default(0);  // Σ denominaciones — lo calcula el service
            $table->decimal('expensesTotal', 14, 2)->default(0);     // Σ expense ligados (denormalizado)
            $table->decimal('payablesTotal', 14, 2)->default(0);     // Σ payable ligados (informativo)
            $table->decimal('expectedCash', 14, 2)->default(0);      // calculado
            $table->decimal('overShort', 14, 2)->default(0);         // descuadre: counted − expected

            $table->string('status', 10)->default('OPEN');          // CashSessionStatus
            $table->text('notes')->nullable();

            $table->foreignId('openedByUserId')->nullable()->constrained('user')->nullOnDelete();
            $table->foreignId('closedByUserId')->nullable()->constrained('user')->nullOnDelete();
            $table->timestamp('closedAt')->nullable();

            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();

            $table->unique(['businessDate'], 'cash_session_unique_business_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_session');
    }
};
