<?php
declare(strict_types=1);

// Forzar salida JSON ante errores fatales en cualquier servidor (Apache/IIS/etc).
ini_set('display_errors', '0');
ini_set('log_errors', '1');

$bootstrapRoot = dirname(__DIR__);
$bootstrapLogDir = $bootstrapRoot . '/storage/logs';
if (!is_dir($bootstrapLogDir)) {
    @mkdir($bootstrapLogDir, 0775, true);
}

set_error_handler(function (int $severity, string $message, string $file = '', int $line = 0): void {
    // Convierte cualquier warning/notice en excepción para que lo capture el handler global.
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function (Throwable $e) use ($bootstrapLogDir): void {
    $payload = ['error' => 'Error interno', 'detail' => $e->getMessage()];
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    @file_put_contents(
        $bootstrapLogDir . '/php-api-error.log',
        '[' . date('c') . '] ' . $e->getMessage() . ' @ ' . ($e->getFile() . ':' . $e->getLine()) . PHP_EOL,
        FILE_APPEND
    );
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
});

register_shutdown_function(function () use ($bootstrapLogDir): void {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        $payload = ['error' => 'Error interno', 'detail' => $err['message']];
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        @file_put_contents(
            $bootstrapLogDir . '/php-api-error.log',
            '[' . date('c') . '] ' . $err['message'] . ' @ ' . ($err['file'] . ':' . $err['line']) . PHP_EOL,
            FILE_APPEND
        );
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }
});
