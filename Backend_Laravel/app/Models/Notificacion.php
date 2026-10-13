<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Notificación del usuario (tabla `notificacion`). */
class Notificacion extends Model
{
    protected $table = 'notificacion';
    protected $primaryKey = 'id_notificacion';

    const CREATED_AT = 'fecha_hora';
    const UPDATED_AT = null;

    protected $fillable = ['tipo', 'mensaje', 'estado', 'id_recordatorio'];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    public function recordatorio(): BelongsTo
    {
        return $this->belongsTo(Recordatorio::class, 'id_recordatorio', 'id_recordatorio');
    }

    public function scopePendientes($q)
    {
        return $q->where('estado', 'pendiente');
    }
}
