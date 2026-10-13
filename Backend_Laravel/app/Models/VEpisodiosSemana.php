<?php

namespace App\Models;

/** Vista `v_episodios_semana` (solo lectura). Usar siempre ->delUsuario($idUsuario) para no mezclar datos de usuarios. */
class VEpisodiosSemana extends VistaSoloLectura
{
    protected $table = 'v_episodios_semana';
}
