<?php
require __DIR__ . '/conexion.php';
solo_metodo('POST');

$in     = entrada();
$nombre = trim((string)($in['name'] ?? ''));
$correo = strtolower(trim((string)($in['email'] ?? '')));
$clave  = (string)($in['password'] ?? '');

if (mb_strlen($nombre) < 2 || mb_strlen($nombre) > 60 ||
    !preg_match('/^[A-Za-zÁÉÍÓÚÑÜáéíóúñü ]+$/u', $nombre)) {
    responder(['error' => 'Nombre inválido'], 422);
}
if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || strlen($correo) > 60) {
    responder(['error' => 'Correo inválido'], 422);
}
if (strlen($clave) < 6 || strlen($clave) > 25 || preg_match('/\s/', $clave)) {
    responder(['error' => 'Contraseña inválida (6 a 25 caracteres, sin espacios)'], 422);
}

$pdo = conectar();
try {
    $st = $pdo->prepare('CALL sp_registrar_usuario(?, ?, ?, @id)');
    $st->execute([$nombre, $correo, password_hash($clave, PASSWORD_BCRYPT)]);
    $st->closeCursor();
    $id = (int)$pdo->query('SELECT @id')->fetchColumn();
} catch (PDOException $e) {
    $sqlstate = $e->errorInfo[0] ?? '';
    $codigo   = $e->errorInfo[1] ?? 0;
    if ($sqlstate === '45000') responder(['error' => $e->errorInfo[2]], 409);
    if ($codigo === 1062)      responder(['error' => 'El correo ya está registrado'], 409);
    throw $e;
}

iniciar_sesion();
session_regenerate_id(true);
$_SESSION['id_usuario'] = $id;

responder(['ok' => true, 'usuario' => cargar_usuario($pdo, $id), 'perfil' => null], 201);