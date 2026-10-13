<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Catálogo de consumo de cafeína (tabla `consumo_cafeina`). */
class ConsumoCafeina extends Model
{
    protected $table = 'consumo_cafeina';
    protected $primaryKey = 'id_consumo_cafeina';
    public $timestamps = false;
    protected $fillable = ['consume_cafeina', 'cantidad_cafeina'];

    public function perfiles(): HasMany
    {
        return $this->hasMany(Perfil::class, 'id_consumo_cafeina', 'id_consumo_cafeina');
    }
}
