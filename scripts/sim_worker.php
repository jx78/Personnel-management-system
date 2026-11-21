<?php
declare(strict_types=1);

$paramsFile = $argv[1] ?? '';
if (!file_exists($paramsFile)) {
    echo json_encode(['error' => 'No params']);
    exit(1);
}

$params = json_decode(file_get_contents($paramsFile), true);

$_SERVER['REQUEST_METHOD'] = $params['method'] ?? 'POST';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
$_POST = $params['post'] ?? [];
$_GET = $params['get'] ?? [];
$_REQUEST = array_merge($_GET, $_POST);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION = $params['session'] ?? [];

register_shutdown_function(function() use ($paramsFile) {
    $sessData = $_SESSION ?? [];
    file_put_contents($paramsFile . '.sess', json_encode($sessData));
});

require $params['script'];

