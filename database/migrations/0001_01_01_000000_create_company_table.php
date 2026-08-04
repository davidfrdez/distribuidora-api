<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla `company` — datos del negocio y parámetros de operación.
 *
 * TIENE UNA SOLA FILA (id = 1). Este sistema es a la medida de una distribuidora:
 * no es multi-tenant, no hay sedes ni aislamiento por cliente, y ninguna tabla del
 * dominio lleva una llave hacia aquí.
 *
 * Está en base de datos y no en `.env` porque son valores que el cliente debe poder
 * cambiar desde la aplicación —el mínimo de pedido o la tolerancia de peso se
 * ajustan con el negocio en marcha— sin necesidad de un despliegue.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('businessName', 200)->nullable();   // razón social
            $table->string('nit', 30)->nullable();
            $table->string('address', 250)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('whatsappPhone', 30)->nullable();
            $table->string('email', 190)->nullable();

            // Registro sanitario propio (obligatorio para manipular cárnicos).
            $table->string('invimaRegistration', 60)->nullable();

            $table->string('timezone', 50)->default('America/Bogota');
            $table->string('currency', 3)->default('COP');

            // Branding
            $table->string('logoPath', 500)->nullable();
            $table->string('brandColor', 20)->nullable();
            $table->string('tagline', 200)->nullable();

            // ── Parámetros de operación ───────────────────────────────────────
            $table->decimal('minOrderAmount', 14, 2)->default(0);
            // Desvío aceptado por defecto entre peso pedido y peso despachado (%).
            // Cada producto puede tener el suyo; si no, se usa este.
            $table->decimal('defaultWeightTolerancePercent', 5, 2)->default(10);
            // Cuánto vive una reserva de stock antes de liberarse sola.
            $table->unsignedSmallInteger('reservationTtlMinutes')->default(240);

            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company');
    }
};
