<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla `expense` — gastos operativos del negocio (aseo, servicios, transporte,
 * jornales…). Es el CONSUMO: plata que sale por operar, distinta de una cuenta
 * por pagar a proveedor (`payable`), que es una obligación con fecha propia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense', function (Blueprint $table) {
            $table->id();
            $table->string('category', 20);                 // ExpenseCategory
            $table->string('description', 200);
            $table->decimal('amount', 14, 2);
            $table->date('expenseDate');
            $table->string('paymentMethod', 20);            // PaymentMethod
            // Un gasto puede (o no) estar asociado a un proveedor concreto.
            $table->foreignId('supplierId')->nullable()->constrained('supplier')->nullOnDelete();
            $table->string('attachmentPath', 300)->nullable();   // soporte (foto/recibo)
            $table->string('notes', 500)->nullable();
            $table->foreignId('createdById')->nullable()->constrained('user')->nullOnDelete();

            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();

            $table->index(['expenseDate'], 'expense_index_date');
            $table->index(['category'], 'expense_index_category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense');
    }
};
