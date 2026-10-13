<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla `reporte` de MigraSense (misma estructura que database/migrasense_corregido.sql).
 * Es "idempotente": si la tabla ya existe (BD importada con el script SQL) NO la toca.
 */
return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('reporte')) {
            return;
        }

        Schema::create('reporte', function (Blueprint $t) {
            $t->bigIncrements('id_reporte');
            $t->unsignedInteger('id_usuario');
            $t->enum('formato', ['pdf', 'txt']);
            $t->date('periodo_inicio');
            $t->date('periodo_fin');
            $t->string('etiqueta', 30)->nullable();
            $t->dateTime('fecha_generacion')->useCurrent();
            $t->index(['id_usuario', 'fecha_generacion'], 'idx_reporte_usuario');
            $t->foreign('id_usuario', 'fk_reporte_usuario')->references('id_usuario')->on('usuario')->cascadeOnDelete();
        });
    }

    /** Intencionalmente vacío: un rollback NUNCA debe borrar datos de salud de los usuarios. */
    public function down()
    {
    }
};
