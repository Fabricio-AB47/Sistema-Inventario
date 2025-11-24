<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';

$user = $_SESSION['user'] ?? null;
if (!$user) {
    respond(401, ['error' => 'No autenticado']);
}
$canManage = (bool)($user['can_manage'] ?? false);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        $pdo = db();
        $stmt = $pdo->query("
            SELECT e.id_equipo,
                   e.modelo,
                   e.marca,
                   e.num_serie AS serie,
                   e.memoria_ram AS ram,
                   e.almacenamiento AS disco,
                   e.hostname,
                   e.precio,
                   e.iva,
                   e.total AS precio_total,
                   e.tiempo_vida_util,
                   e.fecha_adquisicion,
                   e.num_factura,
                   te.descripcion_tp_equipo AS tipo_equipo,
                   es.descripcion_estado_equipo AS estado_equipo,
                   ea.descripcion_estado_activo AS estado_activo
            FROM equipo e
            INNER JOIN tipo_equipo te ON te.id_tp_equipo = e.id_tp_equipo
            INNER JOIN estado_equipo es ON es.id_estado_equipo = e.id_estado_equipo
            INNER JOIN estado_activo ea ON ea.id_estado_activo = e.id_estado_activo
            ORDER BY e.id_equipo DESC
        ");
        respond(200, $stmt->fetchAll());
    }

    if ($method === 'POST') {
        if (!$canManage) {
            respond(403, ['error' => 'No autorizado']);
        }

        $data = getJsonBody();
        $idEquipo = trim((string)($data['id_equipo'] ?? '')) ?: uniqid('EQ-');

        $tipoEquipo = (int)($data['id_tipo_equipo'] ?? $data['id_tp_equipo'] ?? 0);
        $estadoEquipo = (int)($data['id_estado_equipo'] ?? $data['id_estado'] ?? 0);
        $estadoActivo = (int)($data['id_estado_activo'] ?? $estadoEquipo);
        $tipoActivo = (int)($data['id_tipo_activo'] ?? 1);

        $modelo = trim((string)($data['modelo'] ?? ''));
        $marca = trim((string)($data['marca'] ?? ''));
        $serie = trim((string)($data['serie'] ?? $data['num_serie'] ?? ''));
        $ram = (int)($data['ram'] ?? $data['memoria_ram'] ?? 0);
        $disco = (int)($data['disco'] ?? $data['almacenamiento'] ?? 0);
        $hostname = trim((string)($data['hostname'] ?? $data['procesador'] ?? ''));
        $precio = (float)($data['precio'] ?? 0);
        $iva = (float)($data['iva'] ?? 0);
        $vidaUtil = (int)($data['tiempo_vida_util'] ?? $data['vida_util_anios'] ?? $data['vida_util'] ?? 3);
        $fechaAdq = trim((string)($data['fecha_adquisicion'] ?? '')) ?: date('Y-m-d');
        $numFactura = trim((string)($data['num_factura'] ?? 'N/A'));

        if ($tipoEquipo <= 0 || $estadoEquipo <= 0 || $modelo === '' || $marca === '' || $serie === '' || $precio <= 0) {
            respond(422, ['error' => 'Tipo, estado, modelo, marca, serie y precio son obligatorios']);
        }

        $precioTotal = round($precio + $precio * ($iva / 100), 2);

        $pdo = db();
        $insert = $pdo->prepare("
            INSERT INTO equipo (
                id_equipo, marca, modelo, num_serie, memoria_ram, almacenamiento, hostname,
                precio, iva, total, tiempo_vida_util, fecha_adquisicion, num_factura,
                id_estado_activo, id_estado_equipo, id_tp_equipo, id_tipo_activo
            ) VALUES (
                :id_equipo, :marca, :modelo, :serie, :ram, :disco, :hostname,
                :precio, :iva, :total, :vida_util, :fecha_adq, :num_factura,
                :estado_activo, :estado_equipo, :tipo_equipo, :tipo_activo
            )
        ");
        $insert->execute([
            ':id_equipo' => $idEquipo,
            ':marca' => $marca,
            ':modelo' => $modelo,
            ':serie' => $serie,
            ':ram' => $ram,
            ':disco' => $disco,
            ':hostname' => $hostname,
            ':precio' => $precio,
            ':iva' => $iva,
            ':total' => $precioTotal,
            ':vida_util' => $vidaUtil,
            ':fecha_adq' => $fechaAdq,
            ':num_factura' => $numFactura,
            ':estado_activo' => $estadoActivo,
            ':estado_equipo' => $estadoEquipo,
            ':tipo_equipo' => $tipoEquipo,
            ':tipo_activo' => $tipoActivo
        ]);

        respond(200, [
            'id_equipo' => $idEquipo,
            'modelo' => $modelo,
            'marca' => $marca,
            'serie' => $serie,
            'ram' => $ram,
            'disco' => $disco,
            'hostname' => $hostname,
            'procesador' => $hostname,
            'precio' => $precio,
            'iva' => $iva,
            'precio_total' => $precioTotal,
            'id_tipo_equipo' => $tipoEquipo,
            'id_estado_equipo' => $estadoEquipo
        ]);
    }

    respond(405, ['error' => 'Método no permitido']);
} catch (Throwable $th) {
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
