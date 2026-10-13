<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Perfil clínico del cuestionario inicial (tabla `perfil`, 1:1 con usuario). */
class Perfil extends Model
{
    protected $table = 'perfil';
    protected $primaryKey = 'id_perfil';

    const CREATED_AT = 'fecha_completado';
    const UPDATED_AT = 'fecha_actualizacion';

    protected $fillable = [
        'genero', 'ocupacion', 'antiguedad', 'frecuencia', 'inicio_momento_dia', 'duracion', 'intensidad',
        'conoce_fase_prodromica', 'horas_sueno', 'nivel_estres', 'id_patron', 'id_consumo_cafeina', 'version_cuestionario',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    public function patron(): BelongsTo
    {
        return $this->belongsTo(PatronAparicion::class, 'id_patron', 'id_patron');
    }

    public function cafeina(): BelongsTo
    {
        return $this->belongsTo(ConsumoCafeina::class, 'id_consumo_cafeina', 'id_consumo_cafeina');
    }

    public function condiciones(): BelongsToMany
    {
        return $this->belongsToMany(CondicionMedica::class, 'perfil_condicion', 'id_perfil', 'id_condicion');
    }
}
