<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla `episodio_medicacion` de MigraSense (misma estructura que database/migrasense_corregido.sql).
 * Es "idempotente": si la tabla ya existe (BD importada con el script SQL) NO la toca.
 */
return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('episodio_medicacion')) {
            return;
        }

        Schema::create('episodio_medicacion', function (Blueprint $t) {
            $t->unsignedBigInteger('id_episodio')->primary();
            $t->string('medicamento', 80)->nullable();
            $t->foreign('id_episodio', 'fk_em_episodio')->references('id_episodio')->on('episodio_migrana')->cascadeOnDelete();
        });
    }

    /** Intencionalmente vacío: un rollback NUNCA debe borrar datos de salud de los usuarios. */
    public function down()
    {
    }
};
