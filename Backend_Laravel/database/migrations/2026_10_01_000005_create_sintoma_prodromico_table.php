<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla `sintoma_prodromico` de MigraSense (misma estructura que database/migrasense_corregido.sql).
 * Es "idempotente": si la tabla ya existe (BD importada con el script SQL) NO la toca.
 */
return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('sintoma_prodromico')) {
            return;
        }

        Schema::create('sintoma_prodromico', function (Blueprint $t) {
            $t->tinyIncrements('id_sintoma');
            $t->string('codigo', 30)->unique('uq_sintoma_codigo');
            $t->string('nombre_sintoma', 80);
            $t->string('descripcion', 255)->nullable();
            $t->boolean('activo')->default(true);
        });
    }

    /** Intencionalmente vacío: un rollback NUNCA debe borrar datos de salud de los usuarios. */
    public function down()
    {
    }
};
