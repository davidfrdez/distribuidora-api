<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla `warehouse_type` — tipos de bodega configurables
 * (congelación, refrigeración, seco, despacho, cuarentena).
 *
 * Es tabla y no enum porque cada negocio nombra y parametriza sus cuartos
 * distinto, y el rango de temperatura por defecto vive aquí.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_type', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20);
            $table->string('name', 80);
            $table->decimal('defaultTempMin', 5, 2)->nullable();
            $table->decimal('defaultTempMax', 5, 2)->nullable();
            $table->boolean('requiresColdChain')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();

            $table->unique(['code'], 'warehouse_type_unique_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_type');
    }
};
