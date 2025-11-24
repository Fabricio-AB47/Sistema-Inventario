<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';

$user = $_SESSION['user'] ?? null;
$canManage = isset($user['can_manage']) && $user['can_manage'] === true;

$pdo = db();
ensureTableExists($pdo);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    switch ($method) {
        case 'GET':
            $stmt = $pdo->query("SELECT * FROM INVENTARIO_ACTAS ORDER BY fecha DESC, id DESC");
            respond(200, $stmt->fetchAll());
        case 'POST':
            authorize($canManage);
            $payload = getJsonBody();
            $entry = normalizePayload($payload);
            $stmt = $pdo->prepare("
                INSERT INTO INVENTARIO_ACTAS (tipo, folio, fecha, responsable, estado, descripcion, observaciones, id_equipo, id_usuario, created_at)
                OUTPUT INSERTED.*
                VALUES (:tipo, :folio, :fecha, :responsable, :estado, :descripcion, :observaciones, :id_equipo, :id_usuario, SYSDATETIME())
            ");
            $stmt->execute($entry);
            $created = $stmt->fetch();
            if ($created && $created['estado'] === 'cerrado') {
                maybeCreateMovimiento($pdo, (string)$created['id_equipo'], (int)$created['id_usuario'], $created['folio'] ?? '');
            }
            respond(200, $created);
        case 'PUT':
        case 'PATCH':
            authorize($canManage);
            $id = (int)($_GET['id'] ?? 0);
            if ($id <= 0) {
                respond(400, ['error' => 'Falta el id']);
            }
            $payload = getJsonBody();
            $fields = [];
            $params = [':id' => $id];
            foreach (['estado', 'descripcion', 'observaciones', 'responsable', 'folio', 'tipo', 'fecha', 'id_equipo', 'id_usuario'] as $key) {
                if (array_key_exists($key, $payload)) {
                    $fields[] = "$key = :$key";
                    $params[":$key"] = $payload[$key];
                }
            }
            if (!$fields) {
                respond(400, ['error' => 'Nada para actualizar']);
            }
            $sql = "UPDATE INVENTARIO_ACTAS SET " . implode(', ', $fields) . " WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $updated = $pdo->query("SELECT * FROM INVENTARIO_ACTAS WHERE id = $id")->fetch();
            if (!$updated) {
                respond(404, ['error' => 'Registro no encontrado']);
            }
            if (($updated['estado'] ?? '') === 'cerrado') {
                maybeCreateMovimiento($pdo, (string)($updated['id_equipo'] ?? ''), (int)($updated['id_usuario'] ?? 0), $updated['folio'] ?? '');
            }
            respond(200, $updated);
        case 'DELETE':
            authorize($canManage);
            $id = (int)($_GET['id'] ?? 0);
            if ($id <= 0) {
                respond(400, ['error' => 'Falta el id']);
            }
            $stmt = $pdo->prepare("DELETE FROM INVENTARIO_ACTAS WHERE id = :id");
            $stmt->execute([':id' => $id]);
            if ($stmt->rowCount() === 0) {
                respond(404, ['error' => 'Registro no encontrado']);
            }
            respond(204, []);
        default:
            respond(405, ['error' => 'Método no permitido']);
    }
} catch (Throwable $th) {
    logDbError($th->getMessage(), [
        'file' => __FILE__,
        'line' => $th->getLine(),
        'method' => $method
    ]);
    respond(500, ['error' => 'Error interno', 'detail' => $th->getMessage()]);
}

function ensureTableExists(PDO $pdo): void
{
    $exists = $pdo->query("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'INVENTARIO_ACTAS'")->fetchColumn();
    if (!$exists) {
        $pdo->exec("
            CREATE TABLE INVENTARIO_ACTAS (
                id INT IDENTITY(1,1) PRIMARY KEY,
                tipo VARCHAR(20) NOT NULL,
                folio VARCHAR(100) NOT NULL,
                fecha DATE NOT NULL,
                responsable VARCHAR(150) NOT NULL,
                estado VARCHAR(30) NOT NULL,
                descripcion VARCHAR(500) NULL,
                observaciones VARCHAR(500) NULL,
                id_equipo NVARCHAR(150) NULL,
                id_usuario INT NULL,
                created_at DATETIME2 NOT NULL DEFAULT SYSDATETIME()
            );
        ");
        return;
    }
    $cols = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'INVENTARIO_ACTAS'")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('id_equipo', $cols, true)) {
        $pdo->exec("ALTER TABLE INVENTARIO_ACTAS ADD id_equipo NVARCHAR(150) NULL");
    } else {
        $type = $pdo->query("
            SELECT DATA_TYPE
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_NAME = 'INVENTARIO_ACTAS' AND COLUMN_NAME = 'id_equipo'
        ")->fetchColumn();
        if ($type && stripos((string)$type, 'char') === false) {
            $pdo->exec("ALTER TABLE INVENTARIO_ACTAS ALTER COLUMN id_equipo NVARCHAR(150) NULL");
        }
    }
    if (!in_array('id_usuario', $cols, true)) {
        $pdo->exec("ALTER TABLE INVENTARIO_ACTAS ADD id_usuario INT NULL");
    }
}

function normalizePayload(array $payload): array
{
    $tipo = strtolower(trim((string)($payload['tipo'] ?? '')));
    if (!in_array($tipo, ['acta', 'recepcion'], true)) {
        respond(422, ['error' => 'El tipo debe ser acta o recepcion']);
    }
    $folio = trim((string)($payload['folio'] ?? ''));
    $fecha = trim((string)($payload['fecha'] ?? ''));
    $responsable = trim((string)($payload['responsable'] ?? ''));
    if ($folio === '' || $fecha === '' || $responsable === '') {
        respond(422, ['error' => 'Folio, fecha y responsable son obligatorios']);
    }
    $estado = $payload['estado'] ?? 'pendiente';
    if (!in_array($estado, ['pendiente', 'progreso', 'cerrado'], true)) {
        $estado = 'pendiente';
    }
    $idEquipo = trim((string)($payload['id_equipo'] ?? ''));
    $idUsuario = (int)($payload['id_usuario'] ?? 0);
    if ($idEquipo === '' || $idUsuario <= 0) {
        respond(422, ['error' => 'Equipo y usuario son obligatorios para el acta']);
    }
    return [
        ':tipo' => $tipo,
        ':folio' => $folio,
        ':fecha' => $fecha,
        ':responsable' => $responsable,
        ':estado' => $estado,
        ':descripcion' => trim((string)($payload['descripcion'] ?? '')),
        ':observaciones' => trim((string)($payload['observaciones'] ?? '')),
        ':id_equipo' => $idEquipo,
        ':id_usuario' => $idUsuario
    ];
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

function authorize(bool $canManage): void
{
    if (!$canManage) {
        respond(403, ['error' => 'No autorizado']);
    }
}

function maybeCreateMovimiento(PDO $pdo, string $idEquipo, int $idUsuario, string $obs = ''): void
{
    if ($idEquipo === '' || $idUsuario <= 0) {
        return;
    }
    $exists = $pdo->query("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'auditoria_movimientos'")->fetchColumn();
    if (!$exists) {
        return;
    }
    $stmt = $pdo->prepare("
        INSERT INTO auditoria_movimientos (tabla_afectada, id_registro_afectado, accion, fecha, detalle, id_user)
        VALUES ('INVENTARIO_ACTAS', :id_registro, :accion, SYSDATETIME(), :detalle, :usuario)
    ");
    $stmt->execute([
        ':id_registro' => $idEquipo,
        ':accion' => 'CIERRE',
        ':detalle' => $obs ?: 'Cierre de acta',
        ':usuario' => $idUsuario
    ]);
}
