<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla `stock` — saldo desnormalizado GLOBAL por producto.
 *
 * Existe sólo por performance: la verdad está en `stock_movement` y en la suma
 * de `lot`. `ReconcileStockJob` compara las tres fuentes y alerta si divergen.
 *
 * `availableUnits` y `availableKg` son COLUMNAS GENERADAS por la base de datos.
 * En DaliOrder el disponible se recalculaba a mano en cada `save()`, lo que
 * permitía que quedara desactualizado si algún camino olvidaba hacerlo; siendo
 * generadas, es imposible que se desincronicen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock', function (Blueprint $table) {
            $table->id();
            $table->foreignId('productId')->constrained('product')->cascadeOnDelete();

            $table->decimal('currentUnits', 16, 4)->default(0);
            $table->decimal('reservedUnits', 16, 4)->default(0);
            $table->decimal('availableUnits', 16, 4)->storedAs('currentUnits - reservedUnits');

            $table->decimal('currentKg', 16, 4)->default(0);
            $table->decimal('reservedKg', 16, 4)->default(0);
            $table->decimal('availableKg', 16, 4)->storedAs('currentKg - reservedKg');

            $table->timestamp('lastMovementAt')->nullable();
            $table->timestamp('lastCountAt')->nullable();
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();

            // El saldo es GLOBAL: una sola fila por producto.
            $table->unique(['productId'], 'stock_unique_product');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock');
    }
};
