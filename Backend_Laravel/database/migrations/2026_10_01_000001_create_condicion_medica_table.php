<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla `condicion_medica` de MigraSense (misma estructura que database/migrasense_corregido.sql).
 * Es "idempotente": si la tabla ya existe (BD importada con el script SQL) NO la toca.
 */
return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('condicion_medica')) {
            return;
        }

        Schema::create('condicion_medica', function (Blueprint $t) {
            $t->tinyIncrements('id_condicion');
            $t->string('codigo', 30)->unique('uq_condicion_codigo');
            $t->string('condicion_medica', 80);
        });
    }

    /** Intencionalmente vacío: un rollback NUNCA debe borrar datos de salud de los usuarios. */
    public function down()
    {
    }
};
