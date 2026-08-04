<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla `unit` — unidades de medida del negocio.
 *
 * `factorToBase` expresa la unidad en términos de la unidad base de su `kind`
 * (kg para WEIGHT, unidad para COUNT, litro para VOLUME). Así 1 lb = 0,45359237
 * y 1 arroba = 12,5. La conversión genérica es una simple regla de tres; sólo
 * las equivalencias que dependen del producto viven en `unit_conversion`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20);
            $table->string('name', 60);
            $table->string('kind', 20);                          // UnitKind
            $table->decimal('factorToBase', 20, 10)->default(1);
            $table->boolean('isBase')->default(false);
            $table->unsignedTinyInteger('decimals')->default(3); // cómo se muestra
            $table->boolean('active')->default(true);
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();

            $table->unique(['code'], 'unit_unique_code');
            $table->index(['kind'], 'unit_index_kind');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit');
    }
};
