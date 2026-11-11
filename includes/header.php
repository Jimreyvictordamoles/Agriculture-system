<?php
require_once __DIR__ . '/auth.php';
$pageTitle = $pageTitle ?? APP_NAME;
$flash     = get_flash();
$user      = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | <?= e(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>assets/css/style.css" rel="stylesheet">
</head>
<body>
<?php if ($user): ?>
<nav class="navbar navbar-expand-lg navbar-dark bg-success">
    <div class="container">
        <a class="navbar-brand" href="<?= BASE_URL ?>dashboard.php"><?= e(APP_NAME) ?></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false"
                aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>dashboard.php">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>modules/farmers/index.php">Farmers</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>modules/farms/index.php">Farms</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>modules/crops/index.php">Crops</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>modules/plantings/index.php">Plantings</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>modules/harvests/index.php">Harvests</a></li>
            </ul>
            <span class="navbar-text me-3">
                <?= e($user['full_name']) ?>
                <span class="badge bg-light text-success"><?= e($user['role_name']) ?></span>
            </span>
            <a class="btn btn-outline-light btn-sm" href="<?= BASE_URL ?>logout.php">Logout</a>
        </div>
    </div>
</nav>
<?php endif; ?>
<main class="container py-4">
<?php if ($flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
        <?= e($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
