<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Preferencia "alerta de posible episodio" (interruptor del perfil).
 * La tabla `recordatorio` solo admite diaria/semanal, así que esta preferencia vive en `usuario`.
 * Ejecutar con:  php artisan migrate --database=mysql_admin
 */
return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasColumn('usuario', 'alerta_riesgo')) {
            Schema::table('usuario', function (Blueprint $table) {
                $table->boolean('alerta_riesgo')->default(true)->after('activo');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('usuario', 'alerta_riesgo')) {
            Schema::table('usuario', function (Blueprint $table) {
                $table->dropColumn('alerta_riesgo');
            });
        }
    }
};
