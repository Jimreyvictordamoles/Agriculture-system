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
<?php
$path = $_SERVER['SCRIPT_NAME'];
$navItems = [
    'Dashboard' => ['dashboard.php',                 '/dashboard.php'],
    'Farmers'   => ['modules/farmers/index.php',     '/modules/farmers/'],
    'Farms'     => ['modules/farms/index.php',       '/modules/farms/'],
    'Crops'     => ['modules/crops/index.php',       '/modules/crops/'],
    'Plantings' => ['modules/plantings/index.php',   '/modules/plantings/'],
    'Harvests'  => ['modules/harvests/index.php',    '/modules/harvests/'],
    'Reports'   => ['reports/index.php',             '/reports/'],
];
?>
<nav class="navbar navbar-expand-xl navbar-dark bg-success shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-semibold" href="<?= BASE_URL ?>dashboard.php"><?= e(APP_NAME) ?></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false"
                aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto">
                <?php foreach ($navItems as $label => [$href, $match]): ?>
                    <li class="nav-item">
                        <a class="nav-link text-white <?= strpos($path, $match) !== false ? 'active fw-semibold border-bottom border-2 border-white' : 'opacity-75' ?>"
                           href="<?= BASE_URL . $href ?>"><?= e($label) ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="d-flex align-items-center gap-2 text-nowrap">
                <span class="text-white"><?= e($user['full_name']) ?></span>
                <span class="badge bg-light text-success"><?= e($user['role_name']) ?></span>
                <a class="btn btn-outline-light btn-sm ms-2" href="<?= BASE_URL ?>logout.php">Logout</a>
            </div>
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
