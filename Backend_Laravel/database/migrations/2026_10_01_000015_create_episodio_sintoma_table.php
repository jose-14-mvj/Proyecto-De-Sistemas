<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla `episodio_sintoma` de MigraSense (misma estructura que database/migrasense_corregido.sql).
 * Es "idempotente": si la tabla ya existe (BD importada con el script SQL) NO la toca.
 */
return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('episodio_sintoma')) {
            return;
        }

        Schema::create('episodio_sintoma', function (Blueprint $t) {
            $t->unsignedBigInteger('id_episodio');
            $t->unsignedTinyInteger('id_sintoma');
            $t->primary(['id_episodio', 'id_sintoma']);
            $t->foreign('id_episodio', 'fk_es_episodio')->references('id_episodio')->on('episodio_migrana')->cascadeOnDelete();
            $t->foreign('id_sintoma', 'fk_es_sintoma')->references('id_sintoma')->on('sintoma_prodromico');
        });
    }

    /** Intencionalmente vacío: un rollback NUNCA debe borrar datos de salud de los usuarios. */
    public function down()
    {
    }
};
