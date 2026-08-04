<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla `stock_movement` — LIBRO MAYOR del inventario. Inmutable: nunca se
 * edita ni se borra una fila. Un error se corrige con un movimiento contrario.
 *
 * `lotId` es NOT NULL a propósito. En DaliOrder podía faltar y el sistema
 * seguía adelante con un `Log::warning`, dejando kilos vendidos sin lote de
 * origen; en cárnicos eso destruye la trazabilidad. Sin lote no hay movimiento.
 *
 * Las cantidades se guardan siempre POSITIVAS; el signo lo da `direction`.
 * Los `*Before` / `*After` son el saldo de la combinación (producto, bodega)
 * antes y después, para poder auditar el kardex línea por línea.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movement', function (Blueprint $table) {
            $table->id();
            $table->foreignId('productId')->constrained('product');
            $table->foreignId('warehouseId')->constrained('warehouse');
            $table->foreignId('lotId')->constrained('lot');   // OBLIGATORIO

            $table->string('type', 30);          // MovementType
            $table->string('direction', 3);      // MovementDirection

            $table->decimal('units', 16, 4)->default(0);
            $table->decimal('kg', 16, 4)->default(0);

            $table->decimal('costPerUnit', 16, 4)->default(0);
            $table->decimal('costPerKg', 16, 4)->default(0);
            $table->decimal('totalCost', 16, 2)->default(0);

            $table->decimal('unitsBefore', 16, 4)->default(0);
            $table->decimal('unitsAfter', 16, 4)->default(0);
            $table->decimal('kgBefore', 16, 4)->default(0);
            $table->decimal('kgAfter', 16, 4)->default(0);

            // Documento que originó el movimiento (pedido, recepción, merma…).
            $table->string('referenceType', 40)->nullable();
            $table->unsignedBigInteger('referenceId')->nullable();

            $table->foreignId('userId')->nullable()->constrained('user')->nullOnDelete();
            $table->string('notes', 500)->nullable();
            $table->timestamp('movementDate');
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();

            $table->index(['productId', 'warehouseId', 'movementDate'], 'stock_movement_index_kardex');
            $table->index('lotId', 'stock_movement_index_lot');
            $table->index(['referenceType', 'referenceId'], 'stock_movement_index_reference');
            $table->index(['type', 'movementDate'], 'stock_movement_index_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movement');
    }
};
