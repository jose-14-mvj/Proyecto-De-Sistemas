<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Registro de síntomas prodrómicos (tabla `registro_sintoma`). */
class RegistroSintoma extends Model
{
    protected $table = 'registro_sintoma';
    protected $primaryKey = 'id_registro';

    const CREATED_AT = 'fecha_creacion';
    const UPDATED_AT = null;

    protected $fillable = ['fecha', 'hora', 'intensidad', 'observacion'];
    protected $casts = ['fecha' => 'date:Y-m-d', 'intensidad' => 'integer'];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    public function sintomas(): BelongsToMany
    {
        return $this->belongsToMany(SintomaProdromico::class, 'detalle_registro_sintoma', 'id_registro', 'id_sintoma');
    }
}
