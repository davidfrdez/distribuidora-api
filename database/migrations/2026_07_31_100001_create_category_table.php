<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla `category` — árbol de categorías del catálogo.
 * Un nivel de anidamiento basta para el negocio (Salsamentaria → Chorizos).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parentId')->nullable();
            $table->string('code', 20);
            $table->string('name', 120);
            $table->string('description', 300)->nullable();
            $table->unsignedSmallInteger('displayOrder')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();

            $table->foreign('parentId')->references('id')->on('category')->nullOnDelete();
            $table->unique(['code'], 'category_unique_code');
            $table->index(['parentId'], 'category_index_parent');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category');
    }
};
