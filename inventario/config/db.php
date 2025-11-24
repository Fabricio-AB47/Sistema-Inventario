<?php
declare(strict_types=1);

// Compat helpers for PHP < 8.0
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        return $needle !== '' && substr($haystack, 0, strlen($needle)) === $needle;
    }
}

if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool
    {
        return $needle !== '' && substr($haystack, -strlen($needle)) === $needle;
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
        $value = normalizeEnvValue($value);
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
    $encrypt = filterVarBool(getenv('DB_ENCRYPT') ?: 'false');
    $trustCert = filterVarBool(getenv('DB_TRUST_SERVER_CERT') ?: 'true');

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    try {
        // Intento 1: SQL Server con pdo_sqlsrv
        if ($driver === 'sqlsrv') {
            if (hasSqlsrvDriver()) {
                $dsn = "sqlsrv:Server={$host},{$port};Database={$name};Encrypt=" . ($encrypt ? 'yes' : 'no') . ";TrustServerCertificate=" . ($trustCert ? 'yes' : 'no');
                $pdo = new PDO($dsn, $user, $pass, $options);
                return $pdo;
            }
            // Degradar a ODBC solo si está disponible
            $driver = 'odbc';
        }

        // Intento 2: ODBC con el driver especificado
        if ($driver === 'odbc') {
            if (!hasOdbcDriver()) {
                throw new RuntimeException('No está disponible pdo_sqlsrv ni pdo_odbc. Instala la extensión de SQL Server para PHP.');
            }
        // Driver con llaves por espacios en el nombre
        $dsn = "odbc:Driver={" . $odbcDriver . "};Server={$host},{$port};Database={$name};Encrypt=" . ($encrypt ? 'yes' : 'no') . ";TrustServerCertificate=" . ($trustCert ? 'yes' : 'no');
            $pdo = new PDO($dsn, $user, $pass, $options);
            return $pdo;
        }

        // Intento 3: MySQL (fallback)
        if ($driver === 'mysql') {
            if (!extension_loaded('pdo_mysql')) {
                throw new RuntimeException('La extensión pdo_mysql no está instalada.');
            }
            $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
            $pdo = new PDO($dsn, $user, $pass, $options);
            return $pdo;
        }

        throw new RuntimeException('Driver de base de datos no soportado o extensión no instalada.');
    } catch (Throwable $e) {
        logDbError($e->getMessage(), [
            'driver' => $driver,
            'host' => $host,
            'port' => $port,
            'name' => $name,
            'encrypt' => $encrypt,
            'trust' => $trustCert
        ]);
        throw $e;
    }
}

/**
 * Limpia valores del .env removiendo comillas envolventes y espacios extra.
 * Evita que credenciales queden con comillas literales y fallen las conexiones.
 */
function normalizeEnvValue(string $raw): string
{
    $value = trim($raw);

    // Elimina comillas simples o dobles si envuelven todo el valor.
    if ($value !== '' && (
        (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
        (str_starts_with($value, "'") && str_ends_with($value, "'"))
    )) {
        $value = substr($value, 1, -1);
    }

    return trim($value);
}

function filterVarBool(string $value): bool
{
    return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false;
}

function hasSqlsrvDriver(): bool
{
    return extension_loaded('pdo_sqlsrv') || extension_loaded('sqlsrv');
}

function hasOdbcDriver(): bool
{
    return extension_loaded('pdo_odbc') || extension_loaded('odbc');
}

/**
 * Guarda errores de conexión en storage/logs/db-error.log sin exponer contraseñas.
 */
function logDbError(string $message, array $context = []): void
{
    $dir = dirname(__DIR__) . '/storage/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $line = '[' . date('c') . '] ' . $message;
    if ($context) {
        $line .= ' | ' . json_encode($context, JSON_UNESCAPED_SLASHES);
    }
    @file_put_contents($dir . '/db-error.log', $line . PHP_EOL, FILE_APPEND);
}
