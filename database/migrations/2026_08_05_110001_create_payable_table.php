<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla `payable` — cuenta por pagar a un proveedor.
 *
 * El caso del cliente: llega el queso hoy, la factura después, y se paga a mes
 * vencido. Son hechos con fechas distintas, por eso la obligación vive aparte
 * del pago (`payable_payment`) y del gasto operativo (`expense`).
 *
 * `paidAmount` se mantiene sincronizado por `PayableService` sumando los pagos;
 * el saldo pendiente es `totalAmount - paidAmount` (derivado, no se guarda).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payable', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplierId')->constrained('supplier');
            $table->string('invoiceNumber', 60)->nullable();   // número de la factura del proveedor
            $table->string('concept', 200);                    // p. ej. "Queso campesino - pedido semanal"

            $table->date('issueDate');                         // fecha de la factura
            $table->date('dueDate')->nullable();               // vencimiento (mes vencido, a X días…)

            $table->decimal('totalAmount', 14, 2);
            $table->decimal('paidAmount', 14, 2)->default(0);

            $table->string('status', 20)->default('PENDING');  // PayableStatus
            $table->string('attachmentPath', 300)->nullable(); // foto de la factura
            $table->string('notes', 500)->nullable();
            $table->foreignId('createdById')->nullable()->constrained('user')->nullOnDelete();
            // El cierre de caja diario sólo AGRUPA cuentas por día; no es la
            // fuente de verdad de cartera (eso sigue siendo `payable` + `status`).
            $table->foreignId('cashSessionId')->nullable()->constrained('cash_session')->nullOnDelete();

            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();

            // "¿Qué se vence esta semana?" y "¿cuánto debo?" filtran por estado + vencimiento.
            $table->index(['status', 'dueDate'], 'payable_index_status_due');
            $table->index(['supplierId'], 'payable_index_supplier');
            $table->index(['cashSessionId'], 'payable_index_cash_session');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payable');
    }
};
