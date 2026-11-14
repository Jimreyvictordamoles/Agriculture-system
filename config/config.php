<?php
// App-wide settings. Loaded by every page (through includes/functions.php).

define('APP_NAME', 'Agriculture Information System');

// The folder the site lives in. Local XAMPP uses the default below;
// a host that serves the site from its own address sets APP_BASE_URL to /
$baseUrl = getenv('APP_BASE_URL');
define('BASE_URL', $baseUrl !== false && $baseUrl !== '' ? $baseUrl : '/agriculture-system/');

if (session_status() === PHP_SESSION_NONE) {
    // True when the visitor reached the site over HTTPS (also behind a hosting proxy)
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $isHttps,
        'httponly' => true,
        'secure'   => !empty($_SERVER['HTTPS']) || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https',
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once __DIR__ . '/db.php';   // creates $pdo
