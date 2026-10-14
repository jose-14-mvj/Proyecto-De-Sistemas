<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla `recuperacion_clave`: códigos de 6 dígitos para "¿Olvidaste tu contraseña?".
 *
 * - El código NUNCA se guarda en claro: solo su huella (HMAC-SHA256 con APP_KEY).
 * - Vence a los 15 minutos, sirve una sola vez y se anula tras 5 intentos fallidos.
 * - Al borrar la cuenta se borran sus códigos (ON DELETE CASCADE).
 * Idempotente: si la tabla ya existe no la toca.
 */
return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('recuperacion_clave')) {
            return;
        }

        Schema::create('recuperacion_clave', function (Blueprint $t) {
            $t->bigIncrements('id_recuperacion');
            $t->unsignedInteger('id_usuario');
            $t->char('codigo_hash', 64);
            $t->unsignedTinyInteger('intentos')->default(0);
            $t->dateTime('fecha_creacion')->useCurrent();
            $t->dateTime('fecha_expira');
            $t->dateTime('fecha_uso')->nullable();
            $t->string('ip', 45)->nullable();
            $t->index(['id_usuario', 'id_recuperacion'], 'idx_recuperacion_usuario');
            $t->foreign('id_usuario', 'fk_recuperacion_usuario')
              ->references('id_usuario')->on('usuario')->cascadeOnDelete();
        });
    }

    /** Intencionalmente vacío (igual que el resto de migraciones de MigraSense). */
    public function down()
    {
    }
};
