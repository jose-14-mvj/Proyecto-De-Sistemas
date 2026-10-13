<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla `perfil` de MigraSense (misma estructura que database/migrasense_corregido.sql).
 * Es "idempotente": si la tabla ya existe (BD importada con el script SQL) NO la toca.
 */
return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('perfil')) {
            return;
        }

        Schema::create('perfil', function (Blueprint $t) {
            $t->increments('id_perfil');
            $t->unsignedInteger('id_usuario')->unique('uq_perfil_usuario');
            $t->enum('genero', ['femenino', 'masculino', 'otro']);
            $t->enum('ocupacion', ['estudiante', 'oficina_virtual', 'fisico_campo', 'turnos_nocturno', 'hogar', 'sin_ocupacion']);
            $t->enum('antiguedad', ['menos_1_anio', '1_3_anios', '4_10_anios', 'mas_10_anios']);
            $t->enum('frecuencia', ['menos_1_mes', '1_3_mes', '1_2_semana', '3_mas_semana', 'casi_diario']);
            $t->enum('inicio_momento_dia', ['madrugada', 'manana', 'tarde', 'noche', 'sin_horario']);
            $t->enum('duracion', ['menos_4h', '4_12h', '12_24h', '1_3d', 'mas_3d']);
            $t->enum('intensidad', ['leve', 'moderada', 'intensa', 'muy_intensa']);
            $t->enum('conoce_fase_prodromica', ['si', 'no', 'no_seguro']);
            $t->enum('horas_sueno', ['menos_5', '5_6', '7_8', 'mas_8']);
            $t->enum('nivel_estres', ['bajo', 'moderado', 'alto', 'muy_alto']);
            $t->unsignedTinyInteger('id_patron');
            $t->unsignedTinyInteger('id_consumo_cafeina');
            $t->unsignedTinyInteger('version_cuestionario')->default(2);
            $t->dateTime('fecha_completado')->useCurrent();
            $t->dateTime('fecha_actualizacion')->useCurrent()->useCurrentOnUpdate();
            $t->foreign('id_usuario', 'fk_perfil_usuario')->references('id_usuario')->on('usuario')->cascadeOnDelete();
            $t->foreign('id_patron', 'fk_perfil_patron')->references('id_patron')->on('patron_aparicion');
            $t->foreign('id_consumo_cafeina', 'fk_perfil_cafeina')->references('id_consumo_cafeina')->on('consumo_cafeina');
        });
    }

    /** Intencionalmente vacío: un rollback NUNCA debe borrar datos de salud de los usuarios. */
    public function down()
    {
    }
};
