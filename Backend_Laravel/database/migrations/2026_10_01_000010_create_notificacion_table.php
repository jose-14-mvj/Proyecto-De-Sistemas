<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla `notificacion` de MigraSense (misma estructura que database/migrasense_corregido.sql).
 * Es "idempotente": si la tabla ya existe (BD importada con el script SQL) NO la toca.
 */
return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('notificacion')) {
            return;
        }

        Schema::create('notificacion', function (Blueprint $t) {
            $t->bigIncrements('id_notificacion');
            $t->unsignedInteger('id_usuario');
            $t->unsignedInteger('id_recordatorio')->nullable();
            $t->enum('tipo', ['recordatorio_diario', 'resumen_semanal', 'alerta_riesgo', 'sistema']);
            $t->string('mensaje', 255);
            $t->enum('estado', ['pendiente', 'enviada', 'leida', 'descartada'])->default('pendiente');
            $t->dateTime('fecha_hora')->useCurrent();
            $t->index(['id_usuario', 'estado', 'fecha_hora'], 'idx_notif_usuario');
            $t->foreign('id_usuario', 'fk_notif_usuario')->references('id_usuario')->on('usuario')->cascadeOnDelete();
            $t->foreign('id_recordatorio', 'fk_notif_rec')->references('id_recordatorio')->on('recordatorio')->nullOnDelete();
        });
    }

    /** Intencionalmente vacío: un rollback NUNCA debe borrar datos de salud de los usuarios. */
    public function down()
    {
    }
};
