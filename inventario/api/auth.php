<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';

$adminTipos = [
    1 => 'ADMINISTRADOR',
    5 => 'TI'
];

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = strtolower($_GET['action'] ?? $_POST['action'] ?? '');

try {
    if ($method === 'GET' && $action === 'me') {
        if (!isset($_SESSION['user'])) {
            respond(401, ['error' => 'No autenticado']);
        }
        respond(200, ['user' => $_SESSION['user']]);
    }

    if ($method === 'POST' && $action === 'login') {
        $data = getJsonBody();
        $email = strtolower(trim((string)($data['email'] ?? '')));
        $password = (string)($data['password'] ?? '');

        if ($email === '' || $password === '') {
            respond(422, ['error' => 'Correo y contraseña son obligatorios']);
        }

        $pdo = db();
        $stmt = $pdo->prepare("
            SELECT u.id_user, u.nombre, u.apellidos, u.correo, u.id_tp_perfil, u.pwd, t.descripcion_perfil
            FROM usuario u
            INNER JOIN tipo_perfil t ON t.id_tp_perfil = u.id_tp_perfil
            WHERE LOWER(u.correo) = :correo
        ");
        $stmt->execute([':correo' => $email]);
        $userRow = $stmt->fetch();

        if (!$userRow || (string)$userRow['pwd'] !== $password) {
            respond(401, ['error' => 'Credenciales inválidas']);
        }

        $tipoId = (int)$userRow['id_tp_perfil'];
        $user = [
            'id' => (int)$userRow['id_user'],
            'name' => trim($userRow['nombre'] . ' ' . $userRow['apellidos']),
            'email' => $userRow['correo'],
            'tipo_id' => $tipoId,
            'tipo_nombre' => $userRow['descripcion_perfil'] ?? ($adminTipos[$tipoId] ?? 'SIN ROL'),
            'can_manage' => in_array($tipoId, array_keys($adminTipos), true)
        ];

        $_SESSION['user'] = $user;
        respond(200, ['user' => $user]);
    }

    if ($method === 'POST' && $action === 'logout') {
        $_SESSION = [];
        session_destroy();
        respond(200, ['ok' => true]);
    }

    respond(405, ['error' => 'Acción no permitida']);
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
