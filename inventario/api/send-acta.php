<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php'; // para loadEnv
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool
    {
        return $needle === '' || substr($haystack, -strlen($needle)) === $needle;
    }
}

function envClean(string $key, string $default = ''): string
{
    $v = getenv($key);
    if ($v === false || $v === '') return $default;
    $v = trim((string)$v);
    // remover comillas envolventes
    if ((str_starts_with($v, '"') && str_ends_with($v, '"')) || (str_starts_with($v, "'") && str_ends_with($v, "'"))) {
        $v = substr($v, 1, -1);
    }
    return trim($v);
}

// Cargar env para MAIL_*
$rootEnv = dirname(__DIR__) . '/.env';
if (function_exists('loadEnv')) {
    loadEnv($rootEnv);
} elseif (is_file($rootEnv)) {
    foreach (file($rootEnv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
        if ($k !== '') {
            putenv("$k=$v");
        }
    }
}

if (!isset($_SESSION['user'])) {
    respond(401, ['error' => 'No autenticado']);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'POST';
if ($method !== 'POST') {
    respond(405, ['error' => 'Metodo no permitido']);
}

$payload = json_decode(file_get_contents('php://input') ?: '{}', true);
if (!is_array($payload)) {
    respond(400, ['error' => 'Solicitud invalida']);
}

$to = filter_var($payload['to'] ?? '', FILTER_VALIDATE_EMAIL);
if (!$to) {
    respond(422, ['error' => 'Correo destino requerido']);
}

$pdfBase64 = $payload['pdf_base64'] ?? null;
if (!$pdfBase64) {
    respond(422, ['error' => 'PDF requerido']);
}

// CC desde payload + .env MAIL_CC
$cc = $payload['cc'] ?? [];
$ccList = [];
if (is_string($cc)) {
    $ccList = array_filter(array_map('trim', explode(',', $cc)));
} elseif (is_array($cc)) {
    $ccList = array_filter(array_map('trim', $cc));
}
$defCc = getenv('MAIL_CC') ?: '';
$defList = array_filter(array_map('trim', explode(',', $defCc)));
$ccList = array_unique(array_filter(array_merge($ccList, $defList), static function ($mail) {
    return filter_var($mail, FILTER_VALIDATE_EMAIL);
}));

$subject = trim((string)($payload['subject'] ?? 'Acta de entrega y recepcion'));
$acta = $payload['acta'] ?? [];
$sigEntrega = $payload['firma_entrega'] ?? null;
$sigRecibe = $payload['firma_recibe'] ?? null;

$htmlBody = buildBody($acta);
if ($sigEntrega) {
    $htmlBody .= '<p><strong>Firma entrega:</strong><br><img src="' . htmlentities($sigEntrega) . '" style="max-width:300px;"></p>';
}
if ($sigRecibe) {
    $htmlBody .= '<p><strong>Firma recibe:</strong><br><img src="' . htmlentities($sigRecibe) . '" style="max-width:300px;"></p>';
}

try {
    $mail = new PHPMailer(true);
    $mail->CharSet = 'UTF-8';
    $mail->isSMTP();
    $mail->Host = envClean('MAIL_HOST', 'smtp.gmail.com');
    $mail->SMTPAuth = true;
    $mail->Username = envClean('MAIL_USER', '');
    $mail->Password = envClean('MAIL_PASS', '');
    $secure = strtolower(envClean('MAIL_SECURE', 'tls'));
    $mail->SMTPSecure = $secure === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : ($secure === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS);
    $mail->Port = (int)envClean('MAIL_PORT', '587');

    // From: usar MAIL_FROM si trae correo valido; si no, usar MAIL_USER; si no, fallback
    $mailFromEnv = envClean('MAIL_FROM', '');
    $mailUser = envClean('MAIL_USER', '');
    $fromEmail = '';
    $fromName = 'Inventario';

    if ($mailFromEnv && preg_match('/<([^>]+@[^>]+)>/', $mailFromEnv, $m)) {
        $fromEmail = trim($m[1]);
        $fromName = trim(preg_replace('/<.*>/', '', $mailFromEnv)) ?: 'Inventario';
    } elseif ($mailFromEnv && filter_var($mailFromEnv, FILTER_VALIDATE_EMAIL)) {
        $fromEmail = $mailFromEnv;
    } elseif (filter_var($mailUser, FILTER_VALIDATE_EMAIL)) {
        $fromEmail = $mailUser;
    } else {
        $fromEmail = 'no-reply@inventario.local';
    }
    $mail->setFrom($fromEmail, $fromName);

    $mail->addAddress($to);
    foreach ($ccList as $ccAddr) {
        $mail->addCC($ccAddr);
    }

    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body = $htmlBody;
    $mail->AltBody = 'Acta de entrega/recepcion adjunta.';

    $pdfContent = base64_decode(cleanDataUri($pdfBase64));
    if ($pdfContent === false) {
        respond(422, ['error' => 'PDF invalido']);
    }

    // Guardar en disco
    $storageDir = dirname(__DIR__) . '/storage/actas';
    if (!is_dir($storageDir)) {
        mkdir($storageDir, 0775, true);
    }
    $fileName = 'acta-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.pdf';
    $filePath = $storageDir . '/' . $fileName;
    file_put_contents($filePath, $pdfContent);

    $mail->addAttachment($filePath, 'acta.pdf');

    $sent = $mail->send();
    respond(200, ['sent' => $sent, 'file' => $fileName]);
} catch (Exception $e) {
    respond(500, ['error' => 'No se pudo enviar el correo: ' . $e->getMessage()]);
}

function buildBody(array $acta): string
{
    $fields = [
        'folio' => 'Folio',
        'fecha' => 'Fecha',
        'area' => 'Area / Ubicacion',
        'entrega' => 'Entrega',
        'recibe' => 'Recibe',
        'rol' => 'Rol',
        'modelo' => 'Modelo',
        'marca' => 'Marca',
        'ram' => 'RAM',
        'disco' => 'Disco',
        'procesador' => 'Procesador',
        'serie' => 'Serie',
        'precio' => 'Precio',
        'iva' => 'IVA',
        'precio_total' => 'Precio total',
        'asignado' => 'Asignado',
        'reasigna' => 'Reasigna',
        'revision' => 'Revision / Serie',
        'observaciones' => 'Observaciones'
    ];

    $rows = '';
    foreach ($fields as $key => $label) {
        $value = htmlspecialchars((string)($acta[$key] ?? ''), ENT_QUOTES, 'UTF-8');
        $rows .= "<tr><th align=\"left\">{$label}</th><td>{$value}</td></tr>";
    }

    return '<h3>Acta de entrega y recepcion</h3><table border="1" cellspacing="0" cellpadding="6">' . $rows . '</table>';
}

function cleanDataUri(string $dataUri): string
{
    if (str_contains($dataUri, 'base64,')) {
        [, $data] = explode('base64,', $dataUri, 2);
        return $data;
    }
    return $dataUri;
}

function respond(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
