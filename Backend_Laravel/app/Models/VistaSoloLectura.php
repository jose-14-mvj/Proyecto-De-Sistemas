<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Base de los modelos de VISTAS de MySQL (v_*): solo lectura.
 * Cualquier intento de insertar / actualizar / borrar lanza una excepción.
 */
abstract class VistaSoloLectura extends Model
{
    public $timestamps = false;
    public $incrementing = false;
    protected $guarded = ['*'];

    public static function booted()
    {
        foreach (['creating', 'updating', 'deleting', 'saving'] as $evento) {
            static::$evento(function () {
                throw new LogicException('Las vistas son de solo lectura.');
            });
        }
    }

    public function scopeDelUsuario($q, int $idUsuario)
    {
        return $q->where('id_usuario', $idUsuario);
    }
}
