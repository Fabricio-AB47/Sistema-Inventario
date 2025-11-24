<?php
declare(strict_types=1);

// Compat para PHP < 8.0
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        return $needle !== '' && substr($haystack, 0, strlen($needle)) === $needle;
    }
}

// Carga sencilla de variables del archivo .env en $_ENV/$_SERVER si existe.
function loadEnv(string $path): void
{
    if (!is_file($path)) {
        return;
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#')) {
            continue;
        }
        [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
        $key = trim($key);
        $value = trim($value);
        if ($key === '') {
            continue;
        }
        putenv("$key=$value");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $root = dirname(__DIR__);
    loadEnv($root . '/.env');

    $engine = strtolower(getenv('DB_ENGINE') ?: '');
    $driver = getenv('DB_DRIVER') ?: ($engine === 'mssql' ? 'sqlsrv' : 'sqlsrv');
    $host = getenv('DB_HOST') ?: 'localhost';
    $port = getenv('DB_PORT') ?: '1433';
    $name = getenv('DB_NAME') ?: 'inventario';
    $user = getenv('DB_USER') ?: '';
    $pass = getenv('DB_PASS') ?: getenv('DB_PASSWORD') ?: '';
    $odbcDriver = getenv('DB_ODBC_DRIVER') ?: 'ODBC Driver 17 for SQL Server';

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    // Intento 1: sqlsrv
    if ($driver === 'sqlsrv') {
        if (!extension_loaded('sqlsrv')) {
            // Degradar a ODBC si está configurado
            $driver = 'odbc';
        } else {
            $dsn = "sqlsrv:Server={$host},{$port};Database={$name}";
            $pdo = new PDO($dsn, $user, $pass, $options);
            return $pdo;
        }
    }

    // Intento 2: ODBC con el driver especificado
    if ($driver === 'odbc') {
        // Driver con llaves por espacios en el nombre
        $dsn = "odbc:Driver={" . $odbcDriver . "};Server={$host},{$port};Database={$name}";
        $pdo = new PDO($dsn, $user, $pass, $options);
        return $pdo;
    }

    // Intento 3: MySQL (fallback)
    if ($driver === 'mysql') {
        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, $options);
        return $pdo;
    }

    throw new RuntimeException('Driver de base de datos no soportado o extensión no instalada.');
}
