<?php

namespace App\Models;

/** Vista `v_historial` (solo lectura). Usar siempre ->delUsuario($idUsuario) para no mezclar datos de usuarios. */
class VHistorial extends VistaSoloLectura
{
    protected $table = 'v_historial';
}
