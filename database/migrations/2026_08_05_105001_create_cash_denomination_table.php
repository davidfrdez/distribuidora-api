<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla `cash_denomination` — arqueo de efectivo por denominación (billetes y
 * monedas) de un cierre de caja diario. El valor (`denomination × quantity`)
 * no se guarda: lo calcula `CashSessionService::recalculate()`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_denomination', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cashSessionId')->constrained('cash_session')->cascadeOnDelete();

            $table->unsignedInteger('denomination'); // 50,100,200,500,1000,2000,5000,10000,20000,50000,100000
            $table->unsignedInteger('quantity')->default(0);

            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();

            $table->unique(['cashSessionId', 'denomination'], 'cash_denomination_unique_session_denom');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_denomination');
    }
};
