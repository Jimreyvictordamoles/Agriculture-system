<?php
// Reads credentials from real environment variables (Render, etc.)
// and falls back to the local .env file for XAMPP/local use.
$fileEnv = file_exists(__DIR__ . '/../.env') ? parse_ini_file(__DIR__ . '/../.env') : [];

$cfg = function (string $key, string $default = '') use ($fileEnv): string {
    $value = getenv($key);
    if ($value !== false && $value !== '') {
        return $value;
    }
    return (string) ($fileEnv[$key] ?? $default);
};

try {
    $pdo = new PDO(
        "pgsql:host={$cfg('DB_HOST')};port={$cfg('DB_PORT', '5432')};dbname={$cfg('DB_NAME', 'postgres')};sslmode=require",
        $cfg('DB_USER'),
        $cfg('DB_PASSWORD'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    error_log($e->getMessage());
    die('Database connection failed.');
}