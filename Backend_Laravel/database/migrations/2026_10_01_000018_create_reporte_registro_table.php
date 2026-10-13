<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla `reporte_registro` de MigraSense (misma estructura que database/migrasense_corregido.sql).
 * Es "idempotente": si la tabla ya existe (BD importada con el script SQL) NO la toca.
 */
return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('reporte_registro')) {
            return;
        }

        Schema::create('reporte_registro', function (Blueprint $t) {
            $t->unsignedBigInteger('id_reporte');
            $t->unsignedBigInteger('id_registro');
            $t->primary(['id_reporte', 'id_registro']);
            $t->foreign('id_reporte', 'fk_rr_reporte')->references('id_reporte')->on('reporte')->cascadeOnDelete();
            $t->foreign('id_registro', 'fk_rr_registro')->references('id_registro')->on('registro_sintoma')->cascadeOnDelete();
        });
    }

    /** Intencionalmente vacío: un rollback NUNCA debe borrar datos de salud de los usuarios. */
    public function down()
    {
    }
};
