<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla `lot` — lotes de producto. Unidad mínima de trazabilidad: TODO
 * movimiento del kardex apunta a un lote, sin excepción.
 *
 * Doble saldo, la decisión estructural del sistema:
 *   `currentUnits` → piezas / paquetes físicos
 *   `currentKg`    → peso
 * Un producto de peso variable consume ambos a la vez y a ritmos distintos:
 * de una canastilla con 20 chorizos y 12,5 kg pueden salir 3 chorizos y 0,31 kg.
 *
 * `supplierLotCode` es el lote del FABRICANTE. Es el que aparece en un retiro
 * de producto ordenado por la autoridad sanitaria, y por eso no puede faltar
 * aunque el consecutivo interno (`code`) ya identifique el lote.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lot', function (Blueprint $table) {
            $table->id();
            $table->foreignId('productId')->constrained('product')->cascadeOnDelete();
            $table->foreignId('supplierId')->nullable()->constrained('supplier')->nullOnDelete();

            $table->string('code', 40);                       // consecutivo interno: LOT-000123
            $table->string('supplierLotCode', 60)->nullable(); // lote del fabricante
            $table->string('purchaseInvoice', 60)->nullable();

            // ── Doble saldo ───────────────────────────────────────────────────
            $table->decimal('initialUnits', 16, 4)->default(0);
            $table->decimal('currentUnits', 16, 4)->default(0);
            $table->decimal('initialKg', 16, 4)->default(0);
            $table->decimal('currentKg', 16, 4)->default(0);

            // ── Costo (snapshot inmutable de la recepción) ────────────────────
            $table->decimal('costPerUnit', 16, 4)->default(0);
            $table->decimal('costPerKg', 16, 4)->default(0);
            $table->decimal('totalCost', 16, 2)->default(0);

            // ── Fechas: el FIFO ordena por expirationDate ─────────────────────
            $table->date('receivedAt');
            $table->date('expirationDate')->nullable();
            $table->date('manufacturingDate')->nullable();

            $table->string('status', 20)->default('ACTIVE');   // LotStatus
            $table->string('qrCode', 200)->nullable();
            $table->boolean('labelPrinted')->default(false);
            $table->timestamp('labelPrintedAt')->nullable();
            $table->string('notes', 500)->nullable();
            $table->foreignId('receivedById')->nullable()->constrained('user')->nullOnDelete();

            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();
            $table->timestamp('deletedAt')->nullable();

            $table->unique(['code'], 'lot_unique_code');
            // Índice del FIFO: lotes disponibles de un producto, ordenados por
            // vencimiento. Los que no tienen fecha se ordenan por `receivedAt`.
            $table->index(
                ['productId', 'status', 'expirationDate'],
                'lot_index_fifo',
            );
            $table->index(['expirationDate'], 'lot_index_expiring');
            $table->index('supplierLotCode', 'lot_index_supplier_lot');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lot');
    }
};
