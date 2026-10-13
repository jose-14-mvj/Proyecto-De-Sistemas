<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla `consumo_cafeina` de MigraSense (misma estructura que database/migrasense_corregido.sql).
 * Es "idempotente": si la tabla ya existe (BD importada con el script SQL) NO la toca.
 */
return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('consumo_cafeina')) {
            return;
        }

        Schema::create('consumo_cafeina', function (Blueprint $t) {
            $t->tinyIncrements('id_consumo_cafeina');
            $t->enum('consume_cafeina', ['si', 'no']);
            $t->enum('cantidad_cafeina', ['1', '2_3', '4_mas'])->nullable()->unique('uq_cafeina_cantidad');
        });
    }

    /** Intencionalmente vacío: un rollback NUNCA debe borrar datos de salud de los usuarios. */
    public function down()
    {
    }
};
