<?php
require __DIR__ . '/conexion.php';
solo_metodo('POST');

iniciar_sesion();
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();
responder(['ok' => true]);