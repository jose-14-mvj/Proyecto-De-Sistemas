<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Catálogo de patrón de aparición (tabla `patron_aparicion`). */
class PatronAparicion extends Model
{
    protected $table = 'patron_aparicion';
    protected $primaryKey = 'id_patron';
    public $timestamps = false;
    protected $fillable = ['patron_aparicion', 'orden_aparicion'];

    public function perfiles(): HasMany
    {
        return $this->hasMany(Perfil::class, 'id_patron', 'id_patron');
    }
}
