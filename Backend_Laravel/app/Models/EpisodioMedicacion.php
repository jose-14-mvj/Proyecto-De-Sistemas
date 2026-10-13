<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Medicamento tomado en un episodio (tabla `episodio_medicacion`, 1:1 con episodio). */
class EpisodioMedicacion extends Model
{
    protected $table = 'episodio_medicacion';
    protected $primaryKey = 'id_episodio';
    public $incrementing = false;
    public $timestamps = false;
    protected $fillable = ['id_episodio', 'medicamento'];

    public function episodio(): BelongsTo
    {
        return $this->belongsTo(EpisodioMigrana::class, 'id_episodio', 'id_episodio');
    }
}
