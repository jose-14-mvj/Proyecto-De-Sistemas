<?php
require __DIR__ . '/conexion.php';
solo_metodo('POST');

$in     = entrada();
$correo = strtolower(trim((string)($in['email'] ?? '')));
$clave  = (string)($in['password'] ?? '');

$pdo = conectar();
$st  = $pdo->prepare('SELECT id_usuario, contrasena FROM usuario WHERE correo = ? AND activo = 1');
$st->execute([$correo]);
$u = $st->fetch();

if (!$u || !password_verify($clave, $u['contrasena'])) {
    responder(['error' => 'Correo o contraseña incorrectos'], 401);
}

$id = (int)$u['id_usuario'];
iniciar_sesion();
session_regenerate_id(true);
$_SESSION['id_usuario'] = $id;
$pdo->prepare('UPDATE usuario SET ultimo_acceso = NOW() WHERE id_usuario = ?')->execute([$id]);

responder([
    'ok'      => true,
    'usuario' => cargar_usuario($pdo, $id),
    'perfil'  => cargar_perfil($pdo, $id),   // null si aún no hizo el cuestionario
]);