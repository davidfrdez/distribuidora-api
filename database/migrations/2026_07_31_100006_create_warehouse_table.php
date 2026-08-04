<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla `warehouse` — bodegas físicas del negocio.
 *
 * `isQuarantine` marca la bodega a la que va la mercancía retenida (devoluciones
 * por revisar, sospecha de calidad, cadena de frío rota). El stock que vive ahí
 * NO es vendible: el alistamiento no la considera.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouseTypeId')->nullable()->constrained('warehouse_type')->nullOnDelete();
            $table->string('code', 20);
            $table->string('name', 120);
            $table->string('description', 300)->nullable();
            // Rango propio; si es NULL se hereda del `warehouse_type`.
            $table->decimal('tempMin', 5, 2)->nullable();
            $table->decimal('tempMax', 5, 2)->nullable();
            $table->boolean('isDefault')->default(false);
            $table->boolean('isQuarantine')->default(false);
            $table->boolean('sellable')->default(true);   // su stock se puede despachar
            $table->boolean('active')->default(true);
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();

            $table->unique(['code'], 'warehouse_unique_code');
            $table->index(['active'], 'warehouse_index_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse');
    }
};
