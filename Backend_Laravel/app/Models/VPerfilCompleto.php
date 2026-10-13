<?php

namespace App\Models;

/** Vista `v_perfil_completo` (solo lectura). Usar siempre ->delUsuario($idUsuario) para no mezclar datos de usuarios. */
class VPerfilCompleto extends VistaSoloLectura
{
    protected $table = 'v_perfil_completo';
}
