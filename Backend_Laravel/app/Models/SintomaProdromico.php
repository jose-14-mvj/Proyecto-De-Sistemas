<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Catálogo de síntomas prodrómicos (tabla `sintoma_prodromico`). */
class SintomaProdromico extends Model
{
    protected $table = 'sintoma_prodromico';
    protected $primaryKey = 'id_sintoma';
    public $timestamps = false;
    protected $fillable = ['codigo', 'nombre_sintoma', 'descripcion', 'activo'];
    protected $casts = ['activo' => 'boolean'];

    public function scopeActivos($q)
    {
        return $q->where('activo', 1);
    }

    public function registros(): BelongsToMany
    {
        return $this->belongsToMany(RegistroSintoma::class, 'detalle_registro_sintoma', 'id_sintoma', 'id_registro');
    }

    public function episodios(): BelongsToMany
    {
        return $this->belongsToMany(EpisodioMigrana::class, 'episodio_sintoma', 'id_sintoma', 'id_episodio');
    }
}
