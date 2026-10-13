<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla `detalle_registro_sintoma` de MigraSense (misma estructura que database/migrasense_corregido.sql).
 * Es "idempotente": si la tabla ya existe (BD importada con el script SQL) NO la toca.
 */
return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('detalle_registro_sintoma')) {
            return;
        }

        Schema::create('detalle_registro_sintoma', function (Blueprint $t) {
            $t->unsignedBigInteger('id_registro');
            $t->unsignedTinyInteger('id_sintoma');
            $t->primary(['id_registro', 'id_sintoma']);
            $t->foreign('id_registro', 'fk_drs_registro')->references('id_registro')->on('registro_sintoma')->cascadeOnDelete();
            $t->foreign('id_sintoma', 'fk_drs_sintoma')->references('id_sintoma')->on('sintoma_prodromico');
        });
    }

    /** Intencionalmente vacío: un rollback NUNCA debe borrar datos de salud de los usuarios. */
    public function down()
    {
    }
};
