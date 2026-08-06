<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla `cash_movement` — ingresos y egresos de efectivo dentro de un turno.
 * La suma con signo (según `direction`) sobre la base da el efectivo esperado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_movement', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cashSessionId')->constrained('cash_session')->cascadeOnDelete();
            $table->string('type', 20);        // CashMovementType
            $table->string('direction', 3);    // MovementDirection (IN/OUT)
            $table->decimal('amount', 14, 2);  // siempre positivo; el signo lo da direction
            $table->string('concept', 200);
            $table->foreignId('createdById')->nullable()->constrained('user')->nullOnDelete();

            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();

            $table->index(['cashSessionId'], 'cash_movement_index_session');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_movement');
    }
};
