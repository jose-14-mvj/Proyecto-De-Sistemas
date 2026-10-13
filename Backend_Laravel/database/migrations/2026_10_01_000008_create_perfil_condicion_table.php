<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla `perfil_condicion` de MigraSense (misma estructura que database/migrasense_corregido.sql).
 * Es "idempotente": si la tabla ya existe (BD importada con el script SQL) NO la toca.
 */
return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('perfil_condicion')) {
            return;
        }

        Schema::create('perfil_condicion', function (Blueprint $t) {
            $t->unsignedInteger('id_perfil');
            $t->unsignedTinyInteger('id_condicion');
            $t->primary(['id_perfil', 'id_condicion']);
            $t->foreign('id_perfil', 'fk_pc_perfil')->references('id_perfil')->on('perfil')->cascadeOnDelete();
            $t->foreign('id_condicion', 'fk_pc_condicion')->references('id_condicion')->on('condicion_medica');
        });
    }

    /** Intencionalmente vacío: un rollback NUNCA debe borrar datos de salud de los usuarios. */
    public function down()
    {
    }
};
