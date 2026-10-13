<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla `desencadenante` de MigraSense (misma estructura que database/migrasense_corregido.sql).
 * Es "idempotente": si la tabla ya existe (BD importada con el script SQL) NO la toca.
 */
return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('desencadenante')) {
            return;
        }

        Schema::create('desencadenante', function (Blueprint $t) {
            $t->tinyIncrements('id_desencadenante');
            $t->string('codigo', 30)->unique('uq_desencadenante_codigo');
            $t->string('nombre', 80);
            $t->boolean('activo')->default(true);
        });
    }

    /** Intencionalmente vacío: un rollback NUNCA debe borrar datos de salud de los usuarios. */
    public function down()
    {
    }
};
