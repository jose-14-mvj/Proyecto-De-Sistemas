<?php

namespace App\Models;

/** Vista `v_desencadenantes_frecuentes` (solo lectura). Usar siempre ->delUsuario($idUsuario) para no mezclar datos de usuarios. */
class VDesencadenantesFrecuentes extends VistaSoloLectura
{
    protected $table = 'v_desencadenantes_frecuentes';
}
