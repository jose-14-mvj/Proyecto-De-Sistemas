<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Reporte generado (tabla `reporte`) con los registros y episodios que incluye. */
class Reporte extends Model
{
    protected $table = 'reporte';
    protected $primaryKey = 'id_reporte';

    const CREATED_AT = 'fecha_generacion';
    const UPDATED_AT = null;

    protected $fillable = ['formato', 'periodo_inicio', 'periodo_fin', 'etiqueta'];
    protected $casts = ['periodo_inicio' => 'date:Y-m-d', 'periodo_fin' => 'date:Y-m-d'];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    public function registros(): BelongsToMany
    {
        return $this->belongsToMany(RegistroSintoma::class, 'reporte_registro', 'id_reporte', 'id_registro');
    }

    public function episodios(): BelongsToMany
    {
        return $this->belongsToMany(EpisodioMigrana::class, 'reporte_episodio', 'id_reporte', 'id_episodio');
    }
}
