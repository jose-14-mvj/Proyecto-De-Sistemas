<?php
require __DIR__ . '/conexion.php';
$id  = requerir_sesion();
$pdo = conectar();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    responder(['ok' => true, 'perfil' => cargar_perfil($pdo, $id)]);
}
solo_metodo('POST');

$in = entrada();

$obligatorios = ['genero', 'ocupacion', 'tiempoMigrana', 'frecuencia', 'tieneOrden', 'momentoDia',
                 'duracion', 'intensidad', 'conocesProdromica', 'sueno', 'estres', 'cafeina'];
foreach ($obligatorios as $c) {
    if (!isset($in[$c]) || !is_string($in[$c]) || $in[$c] === '') {
        responder(['error' => "Falta responder: $c"], 422);
    }
}
$condiciones = $in['condiciones'] ?? [];
if (!is_array($condiciones) || count($condiciones) === 0) {
    responder(['error' => 'Selecciona al menos una condición (o "Ninguna")'], 422);
}
foreach ($condiciones as $c) {
    if (!is_string($c) || !preg_match('/^[a-z_]+$/', $c)) {
        responder(['error' => 'Condición inválida'], 422);
    }
}

try {
    $st = $pdo->prepare('CALL sp_guardar_perfil(' . implode(',', array_fill(0, 16, '?')) . ')');
    $st->execute([
        $id,
        $in['genero'], $in['ocupacion'], $in['tiempoMigrana'], $in['frecuencia'],
        $in['tieneOrden'], $in['ordenAparicion'] ?? null,
        $in['momentoDia'], $in['duracion'], $in['intensidad'], $in['conocesProdromica'],
        $in['sueno'], $in['estres'],
        $in['cafeina'], $in['porcionesCafeina'] ?? null,
        implode(',', $condiciones),
    ]);
    $st->closeCursor();
} catch (PDOException $e) {
    error_log($e);
    $r = ['error' => 'No se pudo guardar el cuestionario'];
    if (DEPURAR) $r['detalle'] = $e->getMessage();
    responder($r, 422);
}

responder(['ok' => true, 'perfil' => cargar_perfil($pdo, $id)]);