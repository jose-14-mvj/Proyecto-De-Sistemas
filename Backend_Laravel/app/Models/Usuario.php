<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Tabla `usuario` de MigraSense (NO la tabla `users` de Laravel).
 * La contraseña vive en la columna `contrasena` y siempre se guarda hasheada (Argon2id).
 */
class Usuario extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'usuario';
    protected $primaryKey = 'id_usuario';
    public $timestamps = false;           // la tabla usa fecha_registro / ultimo_acceso

    // Solo estos campos se pueden asignar en masa (protege contra mass-assignment)
    protected $fillable = ['nombres', 'correo', 'contrasena'];

    // Nunca salen en respuestas JSON
    protected $hidden = ['contrasena'];

    protected $casts = [
        'activo'         => 'boolean',
        'fecha_registro' => 'datetime',
        'ultimo_acceso'  => 'datetime',
    ];

    /** Laravel debe leer la contraseña de la columna `contrasena`. */
    public function getAuthPassword()
    {
        return $this->contrasena;
    }

    /** Formato JSON que ya usa el frontend (ver api/conexion.php → cargar_usuario). */
    public function paraFrontend(): array
    {
        return [
            'id'        => (int) $this->id_usuario,
            'name'      => $this->nombres,
            'email'     => $this->correo,
            'photo'     => $this->foto,
            'createdAt' => optional($this->fecha_registro)->format('Y-m-d\TH:i:s'),
        ];
    }
}
