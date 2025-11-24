<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';

$user = $_SESSION['user'] ?? null;
if (!$user) {
    respond(401, ['error' => 'No autenticado']);
}
$canManage = (bool)($user['can_manage'] ?? false);
if (!$canManage) {
    respond(403, ['error' => 'No autorizado']);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        $pdo = db();
        $stmt = $pdo->query("
            SELECT u.id_user AS id_usuario,
                   u.nombre AS nombres,
                   u.apellidos,
                   u.cedula,
                   u.correo,
                   u.id_tp_perfil AS id_tipo_usuario,
                   t.descripcion_perfil AS nombre_tipo
            FROM usuario u
            INNER JOIN tipo_perfil t ON t.id_tp_perfil = u.id_tp_perfil
            ORDER BY u.id_user DESC
        ");
        respond(200, $stmt->fetchAll());
    }

    if ($method === 'POST') {
        $data = getJsonBody();
        $nombres = trim((string)($data['nombres'] ?? $data['nombre'] ?? ''));
        $apellidos = trim((string)($data['apellidos'] ?? ''));
        $cedula = trim((string)($data['cedula'] ?? ''));
        $correo = trim((string)($data['correo'] ?? ''));
        $tipoId = (int)($data['id_tipo_usuario'] ?? $data['id_tp_perfil'] ?? 0);
        $psw = (string)($data['psw'] ?? $data['pwd'] ?? '');

        if ($nombres === '' || $apellidos === '' || $correo === '' || $psw === '') {
            respond(422, ['error' => 'nombres, apellidos, correo y contraseña son requeridos']);
        }

        $pdo = db();
        $stmt = $pdo->prepare("SELECT 1 FROM usuario WHERE correo = :correo");
        $stmt->execute([':correo' => $correo]);
        if ($stmt->fetch()) {
            respond(409, ['error' => 'Correo ya existe']);
        }

        $insert = $pdo->prepare("
            INSERT INTO usuario (nombre, apellidos, cedula, correo, id_tp_perfil, pwd)
            VALUES (:nombres, :apellidos, :cedula, :correo, :tipo, :psw)
        ");
        $insert->execute([
            ':nombres' => $nombres,
            ':apellidos' => $apellidos,
            ':cedula' => $cedula,
            ':correo' => $correo,
            ':tipo' => $tipoId ?: 1,
            ':psw' => $psw
        ]);

        $id = (int)$pdo->lastInsertId();
        respond(200, [
            'id_usuario' => $id,
            'nombres' => $nombres,
            'apellidos' => $apellidos,
            'cedula' => $cedula,
            'correo' => $correo,
            'id_tipo_usuario' => $tipoId ?: 1
        ]);
    }

    if ($method === 'PUT' || $method === 'PATCH') {
        $data = getJsonBody();
        $id = (int)($_GET['id'] ?? $data['id'] ?? $data['id_usuario'] ?? 0);
        if ($id <= 0) {
            respond(400, ['error' => 'Falta id de usuario']);
        }

        $updates = [];
        $params = [':id' => $id];
        $map = [
            'nombre' => 'nombre',
            'nombres' => 'nombre',
            'apellidos' => 'apellidos',
            'cedula' => 'cedula',
            'correo' => 'correo'
        ];
        foreach ($map as $input => $col) {
            if (array_key_exists($input, $data)) {
                $val = trim((string)$data[$input]);
                $updates[] = "$col = :$col";
                $params[":$col"] = $val;
            }
        }
        if (isset($data['id_tipo_usuario']) || isset($data['id_tp_perfil'])) {
            $updates[] = "id_tp_perfil = :perfil";
            $params[':perfil'] = (int)($data['id_tipo_usuario'] ?? $data['id_tp_perfil']);
        }
        if (!empty($data['psw']) || !empty($data['pwd'])) {
            $updates[] = "pwd = :pwd";
            $params[':pwd'] = (string)($data['psw'] ?? $data['pwd']);
        }
        if (!$updates) {
            respond(400, ['error' => 'Nada para actualizar']);
        }

        $pdo = db();
        if (isset($params[':correo'])) {
            $dup = $pdo->prepare("SELECT id_user FROM usuario WHERE correo = :correo");
            $dup->execute([':correo' => $params[':correo']]);
            $existing = $dup->fetchColumn();
            if ($existing && (int)$existing !== $id) {
                respond(409, ['error' => 'Correo ya existe']);
            }
        }

        $sql = "UPDATE usuario SET " . implode(', ', $updates) . " WHERE id_user = :id";
        $pdo->prepare($sql)->execute($params);
        $userRow = $pdo->query("SELECT id_user AS id_usuario, nombre AS nombres, apellidos, cedula, correo, id_tp_perfil AS id_tipo_usuario FROM usuario WHERE id_user = $id")->fetch();
        respond(200, $userRow ?: ['ok' => true]);
    }

    respond(405, ['error' => 'Método no permitido']);
} catch (Throwable $th) {
    logDbError($th->getMessage(), [
        'file' => __FILE__,
        'line' => $th->getLine(),
        'method' => $method
    ]);
    respond(500, ['error' => 'Error interno', 'detail' => $th->getMessage()]);
}

function getJsonBody(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '{}', true);
    return is_array($data) ? $data : [];
}

function respond(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
