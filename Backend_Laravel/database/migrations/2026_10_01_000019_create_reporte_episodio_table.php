<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla `reporte_episodio` de MigraSense (misma estructura que database/migrasense_corregido.sql).
 * Es "idempotente": si la tabla ya existe (BD importada con el script SQL) NO la toca.
 */
return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('reporte_episodio')) {
            return;
        }

        Schema::create('reporte_episodio', function (Blueprint $t) {
            $t->unsignedBigInteger('id_reporte');
            $t->unsignedBigInteger('id_episodio');
            $t->primary(['id_reporte', 'id_episodio']);
            $t->foreign('id_reporte', 'fk_re_reporte')->references('id_reporte')->on('reporte')->cascadeOnDelete();
            $t->foreign('id_episodio', 'fk_re_episodio')->references('id_episodio')->on('episodio_migrana')->cascadeOnDelete();
        });
    }

    /** Intencionalmente vacío: un rollback NUNCA debe borrar datos de salud de los usuarios. */
    public function down()
    {
    }
};
