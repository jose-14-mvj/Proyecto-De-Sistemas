<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega "Auras" al catálogo de síntomas (el formulario de episodio ya lo ofrecía y el
 * servidor lo rechazaba con "Síntoma seleccionado no es válido").
 * Idempotente: si ya existe, solo se asegura de que esté activo. No borra nada.
 */
return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('sintoma_prodromico')) {
            return;
        }
        $datos = [
            'nombre_sintoma' => 'Auras',
            'descripcion'    => 'Alteraciones visuales o sensitivas pasajeras (destellos, puntos ciegos, líneas en zigzag, hormigueo) que suelen durar de 5 a 60 minutos, antes o durante el dolor.',
            'activo'         => 1,
        ];
        if (DB::table('sintoma_prodromico')->where('codigo', 'auras')->exists()) {
            DB::table('sintoma_prodromico')->where('codigo', 'auras')->update($datos);
        } else {
            DB::table('sintoma_prodromico')->insert(['codigo' => 'auras'] + $datos);
        }
    }

    /** Intencionalmente vacío: hay registros que ya pueden usar este síntoma. */
    public function down()
    {
    }
};
