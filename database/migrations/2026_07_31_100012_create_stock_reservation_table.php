<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla `stock_reservation` — apartado de stock con dueño y vencimiento.
 *
 * DaliOrder tenía `stock.reservedQuantity` como un número suelto: se sabía
 * cuánto estaba apartado pero no QUIÉN lo apartó ni desde cuándo, así que una
 * reserva huérfana bloqueaba stock para siempre sin forma de rastrearla.
 *
 * Aquí cada reserva conoce su documento origen (`referenceType`/`referenceId`)
 * y expira. `lotId` es opcional: al confirmar un pedido se aparta cantidad del
 * producto, y el lote concreto se decide al alistar (FIFO).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_reservation', function (Blueprint $table) {
            $table->id();
            $table->foreignId('productId')->constrained('product')->cascadeOnDelete();
            $table->foreignId('warehouseId')->constrained('warehouse')->cascadeOnDelete();
            $table->foreignId('lotId')->nullable()->constrained('lot')->nullOnDelete();

            $table->decimal('units', 16, 4)->default(0);
            $table->decimal('kg', 16, 4)->default(0);

            $table->string('status', 20)->default('ACTIVE');   // ReservationStatus
            $table->string('referenceType', 40);               // p. ej. 'order'
            $table->unsignedBigInteger('referenceId');
            $table->timestamp('expiresAt')->nullable();
            $table->timestamp('resolvedAt')->nullable();
            $table->foreignId('createdById')->nullable()->constrained('user')->nullOnDelete();
            $table->string('notes', 300)->nullable();

            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();

            $table->index(['productId', 'warehouseId', 'status'], 'stock_reservation_index_stock');
            $table->index(['referenceType', 'referenceId'], 'stock_reservation_index_reference');
            // Barrido de reservas vencidas.
            $table->index(['status', 'expiresAt'], 'stock_reservation_index_expiry');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_reservation');
    }
};
