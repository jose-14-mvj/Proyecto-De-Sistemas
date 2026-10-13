<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla `registro_sintoma` de MigraSense (misma estructura que database/migrasense_corregido.sql).
 * Es "idempotente": si la tabla ya existe (BD importada con el script SQL) NO la toca.
 */
return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('registro_sintoma')) {
            return;
        }

        Schema::create('registro_sintoma', function (Blueprint $t) {
            $t->bigIncrements('id_registro');
            $t->unsignedInteger('id_usuario');
            $t->date('fecha');
            $t->time('hora');
            $t->unsignedTinyInteger('intensidad');
            $t->string('observacion', 500)->nullable();
            $t->dateTime('fecha_creacion')->useCurrent();
            $t->index(['id_usuario', 'fecha', 'hora'], 'idx_reg_usuario_fecha');
            $t->foreign('id_usuario', 'fk_reg_usuario')->references('id_usuario')->on('usuario')->cascadeOnDelete();
        });
    }

    /** Intencionalmente vacío: un rollback NUNCA debe borrar datos de salud de los usuarios. */
    public function down()
    {
    }
};
