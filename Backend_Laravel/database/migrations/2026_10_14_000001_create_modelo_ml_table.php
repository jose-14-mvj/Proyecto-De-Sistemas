<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla `modelo_ml`: memoria del aprendizaje del modelo de machine learning.
 *
 * Cada vez que el ML se reentrena con los datos de los usuarios queda una fila:
 *   en_curso → activo (pasó el filtro de calidad) | rechazado (acertaba peor) | error
 * El modelo ACTIVO se guarda aquí (archivo en base64) para que no se pierda cuando
 * Render reinicia el servicio de ML. Las filas viejas quedan como "reemplazado" (historial).
 * Idempotente: si la tabla ya existe no la toca.
 */
return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('modelo_ml')) {
            return;
        }

        Schema::create('modelo_ml', function (Blueprint $t) {
            $t->bigIncrements('id_modelo');
            $t->string('version', 40)->nullable();                 // p. ej. 2.0.0-r20261014013000
            $t->string('estado', 15)->default('en_curso');          // en_curso|activo|reemplazado|rechazado|error
            $t->decimal('auc', 5, 3)->nullable();                   // acierto con personas nuevas (0.5 azar, 1 perfecto)
            $t->decimal('auc_anterior', 5, 3)->nullable();
            $t->unsignedInteger('pacientes_reales')->nullable();    // personas reales con las que aprendió
            $t->unsignedInteger('filas_reales')->nullable();
            $t->unsignedInteger('registros_usados')->default(0);    // registros + episodios en la BD al empezar
            $t->string('motivo', 255)->nullable();
            $t->char('sha256', 64)->nullable();
            $t->unsignedInteger('tamano_bytes')->nullable();
            $t->longText('archivo')->nullable();                    // modelo (joblib) en base64, solo si se activó
            $t->dateTime('fecha_inicio')->useCurrent();
            $t->dateTime('fecha_fin')->nullable();
            $t->index(['estado', 'id_modelo'], 'idx_modelo_ml_estado');
        });
    }

    /** Intencionalmente vacío: un rollback no debe borrar lo aprendido. */
    public function down()
    {
    }
};
