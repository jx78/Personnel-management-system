<?php
declare(strict_types=1);

require_once __DIR__ . '/../backend/storage.php';

function denyAccess(): bool
{
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo '403 Forbidden';
    return true;
}

$raw = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';

for ($i = 0; $i < 3; $i++) {
    $decoded = rawurldecode($raw);
    if ($decoded === $raw) {
        break;
    }
    $raw = $decoded;
}

if (str_contains($raw, ':') || preg_match('/[\x00-\x1f\x7f]/', $raw)) {
    return denyAccess();
}

$raw = str_replace('\\', '/', $raw);
$raw = preg_replace('#/+#', '/', $raw);

$segments = [];
foreach (explode('/', $raw) as $seg) {
    if ($seg === '' || $seg === '.') {
        continue;
    }
    if ($seg === '..') {
        array_pop($segments);
    } else {
        $seg = rtrim($seg, ". \t");
        if ($seg !== '') {
            $segments[] = $seg;
        }
    }
}
$path = '/' . implode('/', $segments);
$pathLow = strtolower($path);

$blockedDirs = [
    '/storage/',
    '/database/',
    '/respaldos/',
    '/php/',
];

foreach ($blockedDirs as $dir) {
    if (str_starts_with($pathLow . '/', $dir)) {
        return denyAccess();
    }
}

$ext = strtolower(pathinfo($pathLow, PATHINFO_EXTENSION));
$blockedExts = [
    'db', 'sqlite', 'sqlite3', 'sql', 'bat', 'ps1',
    'sh', 'cmd', 'lock', 'ini', 'bak', 'log', 'env', 'exe'
];

if (in_array($ext, $blockedExts, true)) {
    return denyAccess();
}

$blockedFiles = [
    '/php.ini',
    '/migrar_a_mysql.php',
    '/revisar_sqlite.php',
    '/verificar_conexion_mysql.php',
    '/restablecer_root_mysql.php',
    '/restablecer_admin.php',
    '/diagnostico.php',
    '/router.php',
    '/.gitignore',
    '/.htaccess',
    '/.env',
    '/backend/config.php',
    '/backend/config.example.php',
    '/backend/db.php',
    '/backend/storage.php',
    '/backend/auth_required.php',
    '/backend/config_auth_required.php',
    '/backend/rate_limiter.php',
    '/backend/inicializar_bd.php',
    '/backend/.htaccess',
];

if (in_array($pathLow, $blockedFiles, true)) {
    return denyAccess();
}

$blockedRoots = ['/backend', '/storage', '/database', '/respaldos', '/php'];
if (in_array(rtrim($pathLow, '/'), $blockedRoots, true)) {
    return denyAccess();
}

$docRoot  = $_SERVER['DOCUMENT_ROOT'];
$filePath = $docRoot . $path;

if (is_file($filePath) && $ext !== 'php') {
    return false;
}

if ($path === '/') {
    require $docRoot . '/config/index.php';
    return true;
}

if (is_file($filePath) && $ext === 'php') {
    return false;
}

http_response_code(404);
header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html><body><h2>404 Not Found</h2></body></html>';
return true;
