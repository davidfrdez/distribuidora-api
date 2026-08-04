<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla `unit_conversion` — equivalencias que dependen del PRODUCTO y por eso
 * no se pueden deducir de `unit.factorToBase`.
 *
 * Ejemplos reales: una canastilla de chorizo santarrosano trae 12,5 kg; un
 * paquete de salchicha manguera pesa 0,5 kg. La misma "canastilla" pesa distinto
 * según el producto, así que la equivalencia no puede ser global.
 *
 * `productId` NULL = conversión válida para cualquier producto (empaques estandarizados).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_conversion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('productId')->nullable()->constrained('product')->cascadeOnDelete();
            $table->foreignId('fromUnitId')->constrained('unit');
            $table->foreignId('toUnitId')->constrained('unit');
            $table->decimal('factor', 20, 10);   // 1 fromUnit = factor × toUnit
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();

            $table->unique(
                ['productId', 'fromUnitId', 'toUnitId'],
                'unit_conversion_unique',
            );
            $table->index(['productId'], 'unit_conversion_index_product');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_conversion');
    }
};
