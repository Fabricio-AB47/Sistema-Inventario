<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';

// Compat para PHP < 8
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        return $needle !== '' && substr($haystack, 0, strlen($needle)) === $needle;
    }
}

$user = $_SESSION['user'] ?? null;
if (!$user || empty($user['can_manage'])) {
    respond(403, ['error' => 'No autorizado']);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST') {
    respond(405, ['error' => 'Método no permitido']);
}

$data = json_decode(file_get_contents('php://input') ?: '{}', true);
if (!is_array($data)) {
    respond(400, ['error' => 'Solicitud inválida']);
}

try {
    $idEquipo = trim((string)($data['id_equipo'] ?? ''));
    $idUsuario = (int)($data['id_usuario'] ?? 0);
    $obs = trim((string)($data['observaciones'] ?? ''));
    $estadoAsig = (int)($data['id_estado_asig_reasig'] ?? 0);
    // Se acepta documento_base64 (data URI) o documento_url (data URI); no truncar
    $docUrl = trim((string)($data['documento_base64'] ?? $data['documento_url'] ?? ''));

    if ($idEquipo === '' || $idUsuario <= 0) {
        respond(422, ['error' => 'id_equipo e id_usuario son requeridos']);
    }

    $pdo = db();

    if ($estadoAsig <= 0) {
        $estadoAsig = (int)($pdo->query("SELECT TOP 1 id_estado_asig_reasig FROM estado_asignacion_reasignacion ORDER BY id_estado_asig_reasig")->fetchColumn() ?: 0);
    }
    if ($estadoAsig <= 0) {
        respond(500, ['error' => 'No hay estados de asignación configurados']);
    }

    // Determinar estado actual del equipo y calcular el nuevo
    $estadoActual = (int)($pdo->query("SELECT id_estado_activo FROM equipo WHERE id_equipo = " . $pdo->quote($idEquipo))->fetchColumn() ?: 0);
    $nuevoEstado = $estadoActual;
    if ($estadoActual === 2) { // Disponible
        $nuevoEstado = 1; // Asignado
    } elseif ($estadoActual === 1) { // Asignado
        $nuevoEstado = 5; // Reasignado
    } elseif ($estadoActual === 5) {
        $nuevoEstado = 5;
    }

    // Generar archivo PDF en disco y guardar URL corta
    $docPath = ''; // por defecto cadena vacía para columnas NOT NULL
    if ($docUrl !== '' && str_starts_with($docUrl, 'data:')) {
        $parts = explode(',', $docUrl, 2);
        if (count($parts) === 2) {
            $bin = base64_decode($parts[1], true);
            if ($bin !== false) {
                $dir = dirname(__DIR__) . '/public/docs';
                if (!is_dir($dir)) {
                    @mkdir($dir, 0775, true);
                }
                if (is_dir($dir) && is_writable($dir)) {
                    $filename = 'asignacion-' . preg_replace('/[^A-Za-z0-9_-]/', '_', $idEquipo) . '-' . date('YmdHis') . '.pdf';
                    $full = $dir . '/' . $filename;
                    if (@file_put_contents($full, $bin) !== false) {
                        $docPath = 'docs/' . $filename; // URL relativa
                    }
                }
            }
        }
    }

    // Insertar movimiento; si falla documento_url, reintentar sin guardar la ruta
    $idMov = 0;
    try {
        $insert = $pdo->prepare("
            INSERT INTO asignacion_reasignacion (id_user, id_equipo, fecha_asig_reasig, id_estado_asig_reasig, documento_url)
            VALUES (:usr, :eq, SYSDATETIME(), :estado, :doc);
            SELECT SCOPE_IDENTITY() AS id;
        ");
        $insert->execute([
            ':usr' => $idUsuario,
            ':eq' => $idEquipo,
            ':estado' => $estadoAsig,
            ':doc' => $docPath
        ]);
        $idMov = (int)$pdo->query("SELECT SCOPE_IDENTITY()")->fetchColumn();
    } catch (Throwable $e) {
        $insert = $pdo->prepare("
            INSERT INTO asignacion_reasignacion (id_user, id_equipo, fecha_asig_reasig, id_estado_asig_reasig)
            VALUES (:usr, :eq, SYSDATETIME(), :estado);
            SELECT SCOPE_IDENTITY() AS id;
        ");
        $insert->execute([
            ':usr' => $idUsuario,
            ':eq' => $idEquipo,
            ':estado' => $estadoAsig
        ]);
        $idMov = (int)$pdo->query("SELECT SCOPE_IDENTITY()")->fetchColumn();
        $docPath = null;
    }

    // Actualizar estado del equipo
    if ($nuevoEstado !== $estadoActual && $nuevoEstado > 0) {
        $pdo->prepare("UPDATE equipo SET id_estado_activo = :nuevo WHERE id_equipo = :eq")->execute([
            ':nuevo' => $nuevoEstado,
            ':eq' => $idEquipo
        ]);
    }
    $estadoTxt = $pdo->prepare("SELECT descripcion_estado_activo FROM estado_activo WHERE id_estado_activo = :id");
    $estadoTxt->execute([':id' => $nuevoEstado]);
    $estadoDesc = $estadoTxt->fetchColumn() ?: null;

    $existsAud = $pdo->query("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'auditoria_movimientos'")->fetchColumn();
    if ($existsAud) {
        $aud = $pdo->prepare("
            INSERT INTO auditoria_movimientos (tabla_afectada, id_registro_afectado, accion, fecha, detalle, id_user)
            VALUES ('asignacion_reasignacion', :id_registro, 'ASIGNACION', SYSDATETIME(), :detalle, :usuario)
        ");
        $aud->execute([
            ':id_registro' => (string)$idMov,
            ':detalle' => $obs ?: 'Asignación de equipo',
            ':usuario' => $idUsuario
        ]);
    }

    respond(200, [
        'id_movimiento' => $idMov,
        'id_equipo' => $idEquipo,
        'id_usuario' => $idUsuario,
        'id_estado_asig_reasig' => $estadoAsig,
        'observaciones' => $obs,
        'estado_activo_nuevo' => $nuevoEstado,
        'estado_activo_texto' => $estadoDesc,
        'documento_url' => $docPath
    ]);
} catch (Throwable $th) {
    $ctx = [
        'file' => __FILE__,
        'line' => $th->getLine(),
        'method' => $method
    ];
    logDbError($th->getMessage(), $ctx);
    logApiError($th->getMessage(), $ctx);
    respond(500, ['error' => 'Error interno', 'detail' => $th->getMessage()]);
}

function respond(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Log seguro para errores de API de movimientos.
 */
function logApiError(string $message, array $context = []): void
{
    $line = '[' . date('c') . '] ' . $message;
    if ($context) {
        $line .= ' | ' . json_encode($context, JSON_UNESCAPED_SLASHES);
    }
    $target = dirname(__DIR__) . '/storage/logs/movimientos-error.log';
    if (function_exists('safeLogWrite')) {
        safeLogWrite($target, $line);
        return;
    }
    if (@file_put_contents($target, $line . PHP_EOL, FILE_APPEND) === false) {
        $fallback = rtrim(sys_get_temp_dir(), '/\\') . '/inventario-movimientos-fallback.log';
        @file_put_contents($fallback, $line . PHP_EOL, FILE_APPEND);
    }
}
