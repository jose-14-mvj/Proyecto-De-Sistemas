<?php
require __DIR__ . '/conexion.php';
solo_metodo('GET');

$id = usuario_actual();
if (!$id) responder(['ok' => true, 'autenticado' => false]);

$pdo = conectar();
$st  = $pdo->prepare('SELECT (SELECT COUNT(*) FROM perfil p WHERE p.id_usuario = u.id_usuario) AS tiene
                        FROM usuario u WHERE u.id_usuario = ? AND u.activo = 1');
$st->execute([$id]);
$f = $st->fetch();

if (!$f) {   // la cuenta ya no existe o fue desactivada
    $_SESSION = [];
    session_destroy();
    responder(['ok' => true, 'autenticado' => false]);
}
responder(['ok' => true, 'autenticado' => true, 'tienePerfil' => (int)$f['tiene'] > 0]);