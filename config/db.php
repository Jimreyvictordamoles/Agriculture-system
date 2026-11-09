<?php
$env = parse_ini_file(__DIR__ . '/../.env');

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