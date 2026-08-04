<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla `product` — ENTIDAD ÚNICA del catálogo.
 *
 * A diferencia de DaliOrder (que separa `ingredient` de `product` y los une por
 * receta), aquí lo que se compra y lo que se vende son la misma cosa: una
 * distribuidora compra chorizo y vende chorizo. El despiece y el porcionado se
 * modelan con órdenes de producción (Fase 5), no con recetas.
 *
 * El campo decisivo es `saleMode`:
 *   WEIGHT     → precio por kg, peso variable, se pesa al alistar
 *   UNIT       → precio por unidad, el peso no interviene
 *   FIXED_PACK → precio por unidad, el peso se deriva de `netWeightKg`
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('categoryId')->nullable()->constrained('category')->nullOnDelete();

            $table->string('sku', 40);
            $table->string('name', 200);
            $table->string('description', 500)->nullable();
            $table->string('brand', 100)->nullable();
            $table->string('imageUrl', 500)->nullable();

            // ── Modo de venta y peso ──────────────────────────────────────────
            $table->string('saleMode', 20);                              // SaleMode
            $table->boolean('tracksWeight')->default(true);
            /*
             * FIXED_PACK: peso exacto por unidad (obligatorio).
             * WEIGHT:     peso promedio de una pieza, sólo para estimar el pedido
             *             ("mándeme 3 chorizos" ≈ 0,85 kg cada uno).
             * UNIT:       NULL.
             */
            $table->decimal('netWeightKg', 12, 4)->nullable();
            // Desvío aceptado entre lo pedido y lo despachado. NULL = usar el de `company`.
            $table->decimal('weightTolerancePercent', 5, 2)->nullable();

            // ── Precio y costo ────────────────────────────────────────────────
            // Por kg si saleMode = WEIGHT; por unidad en los otros dos casos.
            $table->decimal('basePrice', 14, 2)->default(0);
            $table->boolean('priceIncludesTax')->default(true);
            $table->decimal('taxPercent', 5, 2)->default(0);   // IVA; muchos cárnicos van exentos
            // Referencia para fijar precio. El costo de venta real sale del lote (FIFO).
            $table->decimal('averageCostPerKg', 14, 4)->default(0);
            $table->decimal('averageCostPerUnit', 14, 4)->default(0);
            $table->decimal('lastCostPerKg', 14, 4)->default(0);
            $table->decimal('lastCostPerUnit', 14, 4)->default(0);
            $table->timestamp('costUpdatedAt')->nullable();

            // ── Unidades ──────────────────────────────────────────────────────
            // Unidad en que se compra al proveedor (canastilla, caja, kg).
            $table->foreignId('purchaseUnitId')->nullable()->constrained('unit')->nullOnDelete();
            // Unidad en que se le muestra al cliente (kg, unidad, paquete).
            $table->foreignId('saleUnitId')->nullable()->constrained('unit')->nullOnDelete();

            // ── Inventario ────────────────────────────────────────────────────
            $table->boolean('trackLots')->default(true);
            $table->unsignedSmallInteger('shelfLifeDays')->nullable();     // vida útil desde fabricación
            $table->unsignedSmallInteger('expirationAlertDays')->default(7);
            $table->decimal('minStockKg', 14, 4)->default(0);
            $table->decimal('maxStockKg', 14, 4)->default(0);
            $table->decimal('minStockUnits', 14, 4)->default(0);
            $table->decimal('maxStockUnits', 14, 4)->default(0);
            // Merma esperada por deshidratación/purga, en % por día de almacenamiento.
            $table->decimal('shrinkagePercentPerDay', 6, 4)->default(0);
            // Rango de temperatura al que debe conservarse (cadena de frío).
            $table->decimal('storageTempMin', 5, 2)->nullable();
            $table->decimal('storageTempMax', 5, 2)->nullable();

            // ── Disponibilidad ────────────────────────────────────────────────
            $table->boolean('sellable')->default(true);
            $table->boolean('purchasable')->default(true);
            $table->boolean('temporarilyOut')->default(false);
            $table->unsignedSmallInteger('displayOrder')->default(0);
            $table->boolean('active')->default(true);

            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();
            $table->timestamp('deletedAt')->nullable();

            $table->unique(['sku'], 'product_unique_sku');
            $table->index(['categoryId'], 'product_index_category');
            $table->index(['active', 'sellable'], 'product_index_sellable');
            $table->index('name', 'product_index_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product');
    }
};
