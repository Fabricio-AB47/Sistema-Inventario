<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user'])) {
    respond(401, ['error' => 'No autenticado']);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'GET') {
    respond(405, ['error' => 'Método no permitido']);
}

try {
    $pdo = db();
    $tipos = $pdo->query("
        SELECT id_tp_equipo AS id_tipo_equipo, descripcion_tp_equipo AS nombre_tipo, descripcion_tp_equipo AS descripcion
        FROM tipo_equipo
        ORDER BY descripcion_tp_equipo
    ")->fetchAll();
    $estados = $pdo->query("
        SELECT id_estado_equipo AS id_estado, descripcion_estado_equipo AS descripcion
        FROM estado_equipo
        ORDER BY descripcion_estado_equipo
    ")->fetchAll();
    $estadosActivos = $pdo->query("
        SELECT id_estado_activo, descripcion_estado_activo
        FROM estado_activo
        ORDER BY descripcion_estado_activo
    ")->fetchAll();
    $tiposActivo = $pdo->query("
        SELECT id_tipo_activo, descripcion_tp_activo
        FROM tipo_activo
        ORDER BY descripcion_tp_activo
    ")->fetchAll();

    respond(200, [
        'tipos_equipo' => $tipos,
        'estados_equipo' => $estados,
        'estados_activo' => $estadosActivos,
        'tipos_activo' => $tiposActivo
    ]);
} catch (Throwable $th) {
    respond(500, ['error' => 'Error interno', 'detail' => $th->getMessage()]);
}

function respond(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
