<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla `payable_payment` — cada pago (total o parcial) contra una cuenta por
 * pagar. La suma de los pagos de una cuenta es su `paidAmount`; llevarlos como
 * filas permite ver el historial de abonos y con qué medio se pagó cada uno.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payable_payment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payableId')->constrained('payable')->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->date('paymentDate');
            $table->string('paymentMethod', 20);            // PaymentMethod
            $table->string('reference', 80)->nullable();    // nro. de transferencia, Nequi, etc.
            $table->string('notes', 300)->nullable();
            $table->foreignId('createdById')->nullable()->constrained('user')->nullOnDelete();

            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();

            $table->index(['payableId'], 'payable_payment_index_payable');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payable_payment');
    }
};
