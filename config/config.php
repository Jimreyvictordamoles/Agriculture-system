<?php
// App-wide settings. Loaded by every page (through includes/functions.php).

define('APP_NAME', 'Agriculture Information System');
define('BASE_URL', '/agriculture-system/');   // must match your folder name inside htdocs

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once __DIR__ . '/db.php';   // creates $pdo