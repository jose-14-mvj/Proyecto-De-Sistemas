<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Recordatorio diario / semanal (tabla `recordatorio`). Máximo uno por (usuario, frecuencia). */
class Recordatorio extends Model
{
    protected $table = 'recordatorio';
    protected $primaryKey = 'id_recordatorio';

    const CREATED_AT = null;
    const UPDATED_AT = 'fecha_actualizacion';

    protected $fillable = ['frecuencia', 'dia_semana', 'hora_configurada', 'activo'];
    protected $casts = ['activo' => 'boolean', 'dia_semana' => 'integer'];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    public function notificaciones(): HasMany
    {
        return $this->hasMany(Notificacion::class, 'id_recordatorio', 'id_recordatorio');
    }
}
