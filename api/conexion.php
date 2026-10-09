<?php
// Conexión PDO a MariaDB (XAMPP) y utilidades comunes de la API
const DEPURAR = true;   // pon false cuando el sitio esté en producción

ini_set('display_errors', '0');          // los avisos de PHP romperían el JSON
date_default_timezone_set('America/La_Paz');

set_exception_handler(function (Throwable $e) {
    error_log($e);   // XAMPP: C:\xampp\php\logs\php_error_log
    $r = ['error' => 'Error interno del servidor'];
    if (DEPURAR) $r['detalle'] = $e->getMessage();
    responder($r, 500);
});

function conectar(): PDO {
    $host = '127.0.0.1';
    $db   = 'migrasense';
    $user = 'migrasense_app';
    $pass = 'migra2026';

    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("SET time_zone = '-04:00'");   // hora de Bolivia
    return $pdo;
}

function responder($datos, int $codigo = 200): void {
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function entrada(): array {
    $d = json_decode(file_get_contents('php://input'), true);
    return is_array($d) ? $d : [];
}

function solo_metodo(string $metodo): void {
    if ($_SERVER['REQUEST_METHOD'] !== $metodo) {
        responder(['error' => 'Método no permitido'], 405);
    }
}

/* ---------- Sesión (cookie de 30 días) ---------- */
function iniciar_sesion(): void {
    if (session_status() !== PHP_SESSION_NONE) return;
    $dias30 = 60 * 60 * 24 * 30;
    ini_set('session.gc_maxlifetime', (string)$dias30);
    session_set_cookie_params([
        'lifetime' => $dias30,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function usuario_actual(): ?int {
    iniciar_sesion();
    return isset($_SESSION['id_usuario']) ? (int)$_SESSION['id_usuario'] : null;
}

function requerir_sesion(): int {
    $id = usuario_actual();
    if (!$id) responder(['error' => 'No autenticado'], 401);
    return $id;
}

/* ---------- Datos de cuenta con el formato que usa el frontend ---------- */
function cargar_usuario(PDO $pdo, int $id): ?array {
    $st = $pdo->prepare('SELECT id_usuario, nombres, correo, foto, fecha_registro
                           FROM usuario WHERE id_usuario = ? AND activo = 1');
    $st->execute([$id]);
    $u = $st->fetch();
    if (!$u) return null;
    return [
        'id'        => (int)$u['id_usuario'],
        'name'      => $u['nombres'],
        'email'     => $u['correo'],
        'photo'     => $u['foto'],
        'createdAt' => str_replace(' ', 'T', $u['fecha_registro']),
    ];
}

function cargar_perfil(PDO $pdo, int $id): ?array {
    $st = $pdo->prepare('SELECT * FROM v_perfil_completo WHERE id_usuario = ?');
    $st->execute([$id]);
    $f = $st->fetch();
    if (!$f || $f['id_perfil'] === null) return null;

    $p = [
        'genero'            => $f['genero'],
        'ocupacion'         => $f['ocupacion'],
        'tiempoMigrana'     => $f['antiguedad'],
        'frecuencia'        => $f['frecuencia'],
        'tieneOrden'        => $f['patron_aparicion'],
        'ordenAparicion'    => $f['orden_aparicion'],
        'momentoDia'        => $f['inicio_momento_dia'],
        'duracion'          => $f['duracion'],
        'intensidad'        => $f['intensidad'],
        'conocesProdromica' => $f['conoce_fase_prodromica'],
        'sueno'             => $f['horas_sueno'],
        'estres'            => $f['nivel_estres'],
        'cafeina'           => $f['consume_cafeina'],
        'porcionesCafeina'  => $f['cantidad_cafeina'],
    ];
    $p = array_filter($p, fn($v) => $v !== null);   // sin claves vacías
    $p['condiciones'] = $f['condiciones'] ? explode(',', $f['condiciones']) : [];
    $p['version']     = 2;
    $p['completedAt'] = str_replace(' ', 'T', $f['fecha_completado']);
    return $p;
}