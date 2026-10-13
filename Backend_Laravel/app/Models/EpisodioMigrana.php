<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Episodio de migraña (tabla `episodio_migrana`). */
class EpisodioMigrana extends Model
{
    protected $table = 'episodio_migrana';
    protected $primaryKey = 'id_episodio';

    const CREATED_AT = 'fecha_creacion';
    const UPDATED_AT = null;

    protected $fillable = ['fecha_inicio', 'hora_inicio', 'duracion', 'nivel_dolor', 'notas'];
    protected $casts = ['fecha_inicio' => 'date:Y-m-d', 'nivel_dolor' => 'integer'];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    public function medicacion(): HasOne
    {
        return $this->hasOne(EpisodioMedicacion::class, 'id_episodio', 'id_episodio');
    }

    public function sintomas(): BelongsToMany
    {
        return $this->belongsToMany(SintomaProdromico::class, 'episodio_sintoma', 'id_episodio', 'id_sintoma');
    }

    public function desencadenantes(): BelongsToMany
    {
        return $this->belongsToMany(Desencadenante::class, 'episodio_desencadenante', 'id_episodio', 'id_desencadenante');
    }
}
