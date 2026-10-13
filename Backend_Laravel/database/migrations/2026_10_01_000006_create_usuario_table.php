<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla `usuario` de MigraSense (misma estructura que database/migrasense_corregido.sql).
 * Es "idempotente": si la tabla ya existe (BD importada con el script SQL) NO la toca.
 */
return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('usuario')) {
            return;
        }

        Schema::create('usuario', function (Blueprint $t) {
            $t->increments('id_usuario');
            $t->string('nombres', 60);
            $t->string('paterno', 40)->nullable();
            $t->string('materno', 40)->nullable();
            $t->string('correo', 60)->unique('uq_usuario_correo');
            $t->string('contrasena', 255);                       // hash Argon2id, nunca texto plano
            $t->date('fecha_nac')->nullable();
            $t->mediumText('foto')->nullable();                  // data URL de la foto de perfil
            $t->boolean('activo')->default(true);
            $t->dateTime('fecha_registro')->useCurrent();
            $t->dateTime('ultimo_acceso')->nullable();
        });
    }

    /** Intencionalmente vacío: un rollback NUNCA debe borrar datos de salud de los usuarios. */
    public function down()
    {
    }
};
