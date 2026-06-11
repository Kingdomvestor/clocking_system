<?php
require_once __DIR__ . '/../include/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();

    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $params['path'] ?? '/',
        'domain' => $params['domain'] ?? '',
        'secure' => $params['secure'] ?? false,
        'httponly' => $params['httponly'] ?? true,
        'samesite' => $params['samesite'] ?? 'Lax',
    ]);
}

session_destroy();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Location: ' . SITE_URL . '/admin/login.php');
exit;