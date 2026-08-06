<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla `cash_session` — turno de caja: de la apertura (con base) al cierre
 * (con arqueo). Sólo puede haber UN turno abierto a la vez; lo garantiza
 * `CashService`, no un índice, porque "abierto" no es único por columna.
 *
 * Al cerrar se guardan las tres cifras del arqueo: lo esperado (base + ingresos
 * − egresos), lo contado físicamente, y la diferencia (descuadre).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_session', function (Blueprint $table) {
            $table->id();
            $table->foreignId('openedById')->constrained('user');
            $table->decimal('openingAmount', 14, 2)->default(0);   // base inicial
            $table->timestamp('openedAt');

            $table->foreignId('closedById')->nullable()->constrained('user')->nullOnDelete();
            $table->decimal('closingExpected', 14, 2)->nullable(); // base + ingresos − egresos
            $table->decimal('closingCounted', 14, 2)->nullable();  // lo contado en el arqueo
            $table->decimal('difference', 14, 2)->nullable();      // contado − esperado
            $table->timestamp('closedAt')->nullable();

            $table->string('status', 10)->default('OPEN');         // CashSessionStatus
            $table->string('notes', 500)->nullable();

            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();

            $table->index(['status'], 'cash_session_index_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_session');
    }
};
