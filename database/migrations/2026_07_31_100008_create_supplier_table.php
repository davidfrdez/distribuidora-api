<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla `supplier` — proveedores. El lote apunta aquí por FK, no por texto libre:
 * ante un retiro de producto hay que poder agrupar por proveedor real.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20);
            $table->string('name', 200);
            $table->string('nit', 30)->nullable();
            $table->string('contactName', 150)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 190)->nullable();
            $table->string('address', 250)->nullable();
            $table->string('city', 100)->nullable();
            // Registro sanitario del establecimiento proveedor (cárnicos).
            $table->string('invimaRegistration', 60)->nullable();
            $table->unsignedSmallInteger('paymentTermDays')->default(0);
            $table->string('notes', 500)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();
            $table->timestamp('deletedAt')->nullable();

            $table->unique(['code'], 'supplier_unique_code');
            $table->index(['active'], 'supplier_index_active');
            $table->index('name', 'supplier_index_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier');
    }
};
