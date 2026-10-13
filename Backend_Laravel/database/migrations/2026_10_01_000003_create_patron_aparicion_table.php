<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla `patron_aparicion` de MigraSense (misma estructura que database/migrasense_corregido.sql).
 * Es "idempotente": si la tabla ya existe (BD importada con el script SQL) NO la toca.
 */
return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('patron_aparicion')) {
            return;
        }

        Schema::create('patron_aparicion', function (Blueprint $t) {
            $t->tinyIncrements('id_patron');
            $t->enum('patron_aparicion', ['si', 'no']);
            $t->enum('orden_aparicion', ['2_3_meses', '4_5_meses', '6_7_meses', '8_9_meses', '9_10_meses'])->nullable()->unique('uq_patron_orden');
        });
    }

    /** Intencionalmente vacío: un rollback NUNCA debe borrar datos de salud de los usuarios. */
    public function down()
    {
    }
};
