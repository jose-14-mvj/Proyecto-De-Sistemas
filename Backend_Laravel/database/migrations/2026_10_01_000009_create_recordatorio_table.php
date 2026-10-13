<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla `recordatorio` de MigraSense (misma estructura que database/migrasense_corregido.sql).
 * Es "idempotente": si la tabla ya existe (BD importada con el script SQL) NO la toca.
 */
return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('recordatorio')) {
            return;
        }

        Schema::create('recordatorio', function (Blueprint $t) {
            $t->increments('id_recordatorio');
            $t->unsignedInteger('id_usuario');
            $t->enum('frecuencia', ['diaria', 'semanal']);
            $t->unsignedTinyInteger('dia_semana')->nullable();   // 0 = domingo … 6 = sábado
            $t->time('hora_configurada');
            $t->boolean('activo')->default(true);
            $t->dateTime('fecha_actualizacion')->useCurrent()->useCurrentOnUpdate();
            $t->unique(['id_usuario', 'frecuencia'], 'uq_recordatorio_usuario_frec');
            $t->foreign('id_usuario', 'fk_rec_usuario')->references('id_usuario')->on('usuario')->cascadeOnDelete();
        });
    }

    /** Intencionalmente vacío: un rollback NUNCA debe borrar datos de salud de los usuarios. */
    public function down()
    {
    }
};
