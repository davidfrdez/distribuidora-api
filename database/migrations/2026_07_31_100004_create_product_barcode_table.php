<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla `product_barcode` — un producto puede tener varios códigos de barras
 * (el del fabricante, el de la caja, el que imprime la báscula).
 *
 * `isWeightEmbedded` marca los códigos EAN-13 de báscula que llevan el peso
 * embebido (prefijo 2x): al escanearlos hay que extraer el peso del propio
 * código en lugar de pedirlo aparte.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_barcode', function (Blueprint $table) {
            $table->id();
            $table->foreignId('productId')->constrained('product')->cascadeOnDelete();
            $table->string('barcode', 60);
            $table->string('label', 60)->nullable();          // "caja x 10", "báscula"
            $table->boolean('isWeightEmbedded')->default(false);
            $table->boolean('isPrimary')->default(false);
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();

            $table->unique(['barcode'], 'product_barcode_unique');
            $table->index('productId', 'product_barcode_index_product');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_barcode');
    }
};
