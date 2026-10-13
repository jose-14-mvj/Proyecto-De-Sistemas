<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Catálogo de desencadenantes (tabla `desencadenante`). */
class Desencadenante extends Model
{
    protected $table = 'desencadenante';
    protected $primaryKey = 'id_desencadenante';
    public $timestamps = false;
    protected $fillable = ['codigo', 'nombre', 'activo'];
    protected $casts = ['activo' => 'boolean'];

    public function episodios(): BelongsToMany
    {
        return $this->belongsToMany(EpisodioMigrana::class, 'episodio_desencadenante', 'id_desencadenante', 'id_episodio');
    }
}
