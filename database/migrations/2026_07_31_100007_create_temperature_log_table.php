<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla `temperature_log` — bitácora de temperatura de cada bodega.
 *
 * DaliOrder guardaba el rango permitido pero no las lecturas. Sin lecturas no
 * hay cadena de frío demostrable: es el registro que exige la autoridad
 * sanitaria y el que respalda una merma por ruptura de frío.
 *
 * `outOfRange` se calcula al registrar y se persiste para poder indexar las
 * desviaciones sin recalcular contra el rango vigente (que puede cambiar).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('temperature_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouseId')->constrained('warehouse')->cascadeOnDelete();
            $table->decimal('temperature', 5, 2);
            // Rango vigente al momento de la lectura (snapshot: el rango puede cambiar).
            $table->decimal('expectedMin', 5, 2)->nullable();
            $table->decimal('expectedMax', 5, 2)->nullable();
            $table->boolean('outOfRange')->default(false);
            $table->string('source', 20)->default('MANUAL');   // MANUAL | SENSOR
            $table->string('notes', 300)->nullable();
            $table->foreignId('recordedById')->nullable()->constrained('user')->nullOnDelete();
            $table->timestamp('recordedAt');
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();

            $table->index(['warehouseId', 'recordedAt'], 'temperature_log_index_warehouse_time');
            $table->index(['outOfRange'], 'temperature_log_index_alerts');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('temperature_log');
    }
};
