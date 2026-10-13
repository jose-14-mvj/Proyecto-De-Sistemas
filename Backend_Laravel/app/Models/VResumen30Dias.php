<?php

namespace App\Models;

/** Vista `v_resumen_30dias` (solo lectura). Usar siempre ->delUsuario($idUsuario) para no mezclar datos de usuarios. */
class VResumen30Dias extends VistaSoloLectura
{
    protected $table = 'v_resumen_30dias';
}
