<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla `episodio_desencadenante` de MigraSense (misma estructura que database/migrasense_corregido.sql).
 * Es "idempotente": si la tabla ya existe (BD importada con el script SQL) NO la toca.
 */
return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('episodio_desencadenante')) {
            return;
        }

        Schema::create('episodio_desencadenante', function (Blueprint $t) {
            $t->unsignedBigInteger('id_episodio');
            $t->unsignedTinyInteger('id_desencadenante');
            $t->primary(['id_episodio', 'id_desencadenante']);
            $t->foreign('id_episodio', 'fk_ed_episodio')->references('id_episodio')->on('episodio_migrana')->cascadeOnDelete();
            $t->foreign('id_desencadenante', 'fk_ed_desenc')->references('id_desencadenante')->on('desencadenante');
        });
    }

    /** Intencionalmente vacío: un rollback NUNCA debe borrar datos de salud de los usuarios. */
    public function down()
    {
    }
};
