<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla `episodio_migrana` de MigraSense (misma estructura que database/migrasense_corregido.sql).
 * Es "idempotente": si la tabla ya existe (BD importada con el script SQL) NO la toca.
 */
return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('episodio_migrana')) {
            return;
        }

        Schema::create('episodio_migrana', function (Blueprint $t) {
            $t->bigIncrements('id_episodio');
            $t->unsignedInteger('id_usuario');
            $t->date('fecha_inicio');
            $t->time('hora_inicio');
            $t->enum('duracion', ['menos_4h', '4_24h', '1_3d', 'mas_3d']);
            $t->unsignedTinyInteger('nivel_dolor');
            $t->string('notas', 500)->nullable();
            $t->dateTime('fecha_creacion')->useCurrent();
            $t->index(['id_usuario', 'fecha_inicio', 'hora_inicio'], 'idx_ep_usuario_fecha');
            $t->foreign('id_usuario', 'fk_ep_usuario')->references('id_usuario')->on('usuario')->cascadeOnDelete();
        });
    }

    /** Intencionalmente vacío: un rollback NUNCA debe borrar datos de salud de los usuarios. */
    public function down()
    {
    }
};
