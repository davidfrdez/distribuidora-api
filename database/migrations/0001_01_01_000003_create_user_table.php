<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla `user` (singular, convención del proyecto) + auxiliares de auth.
 *
 * Login simple: correo y contraseña. Todos los usuarios pertenecen al mismo
 * negocio, así que no hay columna de tenant ni impersonación; lo único que
 * diferencia a un usuario de otro es su `role`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('email', 190)->unique();
            $table->string('password');
            $table->string('role', 30);
            $table->string('phone', 30)->nullable();
            $table->string('documentNumber', 30)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('lastLoginAt')->nullable();
            $table->rememberToken();
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();

            $table->index('role', 'user_index_role');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('user');
    }
};
