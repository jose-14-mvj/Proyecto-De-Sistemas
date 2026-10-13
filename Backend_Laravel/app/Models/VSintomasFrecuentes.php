<?php

namespace App\Models;

/** Vista `v_sintomas_frecuentes` (solo lectura). Usar siempre ->delUsuario($idUsuario) para no mezclar datos de usuarios. */
class VSintomasFrecuentes extends VistaSoloLectura
{
    protected $table = 'v_sintomas_frecuentes';
}
