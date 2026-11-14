<?php
// Database settings come from environment variables when they exist (hosting),
// otherwise from the .env file (local XAMPP).
$env     = [];
$envFile = __DIR__ . '/../.env';
if (is_readable($envFile)) {
    $env = parse_ini_file($envFile) ?: [];
}
foreach (['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD'] as $key) {
    $value = getenv($key);
    if ($value !== false && $value !== '') {
        $env[$key] = $value;
    }
}

foreach (['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD'] as $key) {
    if (!isset($env[$key])) {
        error_log("Database setting $key is missing.");
        die('Database settings are missing.');
    }
}

try {
    $pdo = new PDO(
        "pgsql:host={$env['DB_HOST']};port={$env['DB_PORT']};dbname={$env['DB_NAME']};sslmode=require",
        $env['DB_USER'],
        $env['DB_PASSWORD'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    error_log($e->getMessage());
    die('Database connection failed.');
}
