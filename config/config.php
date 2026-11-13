<?php
// App-wide settings. Loaded by every page (through includes/functions.php).

define('APP_NAME', 'Agriculture Information System');

// "/agriculture-system/" locally (XAMPP). On the live site set the BASE_URL
// environment variable to "/".
define('BASE_URL', getenv('BASE_URL') ?: '/agriculture-system/');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'secure'   => !empty($_SERVER['HTTPS']) || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https',
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once __DIR__ . '/db.php';   // creates $pdo