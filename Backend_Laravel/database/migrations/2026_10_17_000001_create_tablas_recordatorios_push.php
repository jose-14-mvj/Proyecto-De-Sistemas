<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recordatorios que llegan aunque la app esté cerrada.
 * - suscripcion_push: cada navegador/celular que aceptó recibir avisos (Web Push).
 * - envio_recordatorio: qué aviso se envió a quién y cuándo (evita mandar dos veces).
 * Idempotente: si las tablas ya existen no hace nada.
 */
return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('suscripcion_push')) {
            Schema::create('suscripcion_push', function (Blueprint $t) {
                $t->bigIncrements('id_suscripcion');
                $t->unsignedInteger('id_usuario');
                $t->char('endpoint_hash', 64)->unique();      // sha256 del endpoint (el endpoint es largo)
                $t->text('endpoint');
                $t->string('p256dh', 120);
                $t->string('auth', 40);
                $t->string('agente', 160)->nullable();        // navegador, solo para mostrarlo
                $t->dateTime('fecha_creacion')->useCurrent();
                $t->dateTime('ultimo_envio')->nullable();
                $t->index('id_usuario', 'idx_push_usuario');
                $t->foreign('id_usuario', 'fk_push_usuario')->references('id_usuario')->on('usuario')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('envio_recordatorio')) {
            Schema::create('envio_recordatorio', function (Blueprint $t) {
                $t->bigIncrements('id_envio');
                $t->unsignedInteger('id_usuario');
                $t->string('tipo', 10);                       // diario | semanal
                $t->string('clave', 12);                      // 2026-10-16 (día) o 2026-W42 (semana)
                $t->string('resultado', 255)->nullable();
                $t->dateTime('fecha_envio')->useCurrent();
                $t->unique(['id_usuario', 'tipo', 'clave'], 'uq_envio_usuario_tipo_clave');
                $t->foreign('id_usuario', 'fk_envio_usuario')->references('id_usuario')->on('usuario')->cascadeOnDelete();
            });
        }
    }

    /** Intencionalmente vacío: no se borran datos en un rollback. */
    public function down()
    {
    }
};
