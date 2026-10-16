<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * modelo_ml.reporte: lo que aprendió cada modelo en texto legible (JSON del /metricas del ML:
 * con cuántas personas, qué factores pesan más, si mejoró con datos reales, calibración...).
 * El modelo en sí sigue en `archivo` (binario en base64); esto es para poder LEERLO.
 * Idempotente: si la columna ya existe no hace nada.
 */
return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('modelo_ml') && ! Schema::hasColumn('modelo_ml', 'reporte')) {
            Schema::table('modelo_ml', function (Blueprint $t) {
                $t->longText('reporte')->nullable();
            });
        }
    }

    /** Intencionalmente vacío: un rollback no debe borrar lo aprendido. */
    public function down()
    {
    }
};
