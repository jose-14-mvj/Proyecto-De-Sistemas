<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Catálogo de condiciones médicas (tabla `condicion_medica`). */
class CondicionMedica extends Model
{
    protected $table = 'condicion_medica';
    protected $primaryKey = 'id_condicion';
    public $timestamps = false;
    protected $fillable = ['codigo', 'condicion_medica'];

    public function perfiles(): BelongsToMany
    {
        return $this->belongsToMany(Perfil::class, 'perfil_condicion', 'id_condicion', 'id_perfil');
    }
}
