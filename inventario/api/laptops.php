<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$dataFile = __DIR__ . '/../data/laptops.json';

if (!is_file($dataFile)) {
    file_put_contents($dataFile, '[]', LOCK_EX);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    switch ($method) {
        case 'GET':
            $id = $_GET['id'] ?? null;
            $items = readData($dataFile);
            if ($id) {
                $item = findById($items, $id);
                if (!$item) {
                    respond(404, ['error' => 'Registro no encontrado']);
                }
                respond(200, $item);
            }
            respond(200, $items);
            break;
        case 'POST':
            $payload = getJsonBody();
            $entry = normalizePayload($payload);
            $items = readData($dataFile);
            array_unshift($items, $entry);
            writeData($dataFile, $items);
            respond(200, $entry);
            break;
        case 'PUT':
        case 'PATCH':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                respond(400, ['error' => 'Falta el id']);
            }
            $payload = getJsonBody();
            $items = readData($dataFile);
            $updated = null;
            foreach ($items as &$item) {
                if (($item['id'] ?? null) === $id) {
                    $item = array_merge($item, array_filter([
                        'modelo' => $payload['modelo'] ?? null,
                        'marca' => $payload['marca'] ?? null,
                        'ram' => $payload['ram'] ?? null,
                        'disco' => $payload['disco'] ?? null,
                        'serie' => $payload['serie'] ?? null,
                        'precio' => isset($payload['precio']) ? (float)$payload['precio'] : null,
                        'iva' => isset($payload['iva']) ? (float)$payload['iva'] : null,
                        'precio_total' => null,
                        'asignado_a' => $payload['asignado_a'] ?? null,
                        'reasignar' => isset($payload['reasignar']) ? (bool)$payload['reasignar'] : null,
                        'revision' => $payload['revision'] ?? null
                    ], static fn($val) => $val !== null));

                    // Recalcular total si hay precio/iva.
                    if (isset($payload['precio']) || isset($payload['iva'])) {
                        $precio = (float)($item['precio'] ?? 0);
                        $iva = (float)($item['iva'] ?? 0);
                        $item['precio_total'] = calculateTotal($precio, $iva);
                    }

                    $updated = $item;
                    break;
                }
            }
            unset($item);

            if (!$updated) {
                respond(404, ['error' => 'Registro no encontrado']);
            }

            writeData($dataFile, $items);
            respond(200, $updated);
            break;
        case 'DELETE':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                respond(400, ['error' => 'Falta el id']);
            }
            $items = readData($dataFile);
            $newItems = array_values(array_filter($items, fn(array $item) => ($item['id'] ?? '') !== $id));
            if (count($items) === count($newItems)) {
                respond(404, ['error' => 'Registro no encontrado']);
            }
            writeData($dataFile, $newItems);
            respond(204, []);
            break;
        default:
            respond(405, ['error' => 'Método no permitido']);
    }
} catch (Throwable $th) {
    respond(500, ['error' => 'Ocurrió un error interno', 'detail' => $th->getMessage()]);
}

function readData(string $file): array
{
    $contents = file_get_contents($file);
    if ($contents === false) {
        return [];
    }
    $decoded = json_decode($contents, true);
    return is_array($decoded) ? $decoded : [];
}

function writeData(string $file, array $data): void
{
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    file_put_contents($file, $json, LOCK_EX);
}

function getJsonBody(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '{}', true);
    return is_array($data) ? $data : [];
}

function normalizePayload(array $payload): array
{
    $modelo = trim((string)($payload['modelo'] ?? ''));
    $marca = trim((string)($payload['marca'] ?? ''));
    $ram = trim((string)($payload['ram'] ?? ''));
    $disco = trim((string)($payload['disco'] ?? ''));
    $serie = trim((string)($payload['serie'] ?? ''));
    $asignado = trim((string)($payload['asignado_a'] ?? ''));
    $precio = isset($payload['precio']) ? (float)$payload['precio'] : 0.0;
    $iva = isset($payload['iva']) ? (float)$payload['iva'] : 0.0;
    $reasignar = (bool)($payload['reasignar'] ?? false);
    $revision = trim((string)($payload['revision'] ?? ''));

    if ($modelo === '' || $marca === '' || $serie === '' || $asignado === '') {
        respond(422, ['error' => 'Modelo, marca, serie y asignado son obligatorios']);
    }

    $total = calculateTotal($precio, $iva);

    return [
        'id' => uniqid('lap_', true),
        'modelo' => $modelo,
        'marca' => $marca,
        'ram' => $ram,
        'disco' => $disco,
        'serie' => $serie,
        'precio' => $precio,
        'iva' => $iva,
        'precio_total' => $total,
        'asignado_a' => $asignado,
        'reasignar' => $reasignar,
        'revision' => $revision,
        'created_at' => date('c')
    ];
}

function calculateTotal(float $precio, float $iva): float
{
    return round($precio + ($precio * ($iva / 100)), 2);
}

function findById(array $items, string $id): ?array
{
    foreach ($items as $item) {
        if (($item['id'] ?? null) === $id) {
            return $item;
        }
    }
    return null;
}

function respond(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
