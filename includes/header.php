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
    <style>
        /* Navbar polish (kept here so style.css stays untouched) */
        .app-nav .nav-link        { border-radius: .4rem; padding: .45rem .75rem; transition: background-color .15s, opacity .15s; }
        .app-nav .nav-link:hover  { opacity: 1 !important; background-color: rgba(255, 255, 255, .12); }
        .app-nav .nav-link.active { background-color: rgba(255, 255, 255, .2); }

        .user-toggle       { display: flex; align-items: center; gap: .6rem; padding: .25rem .6rem .25rem .3rem;
                             color: #fff; background: transparent; border: 1px solid rgba(255, 255, 255, .35);
                             border-radius: 2rem; transition: background-color .15s; }
        .user-toggle:hover,
        .user-toggle.show  { background-color: rgba(255, 255, 255, .15); color: #fff; }
        .user-toggle:focus-visible { outline: 2px solid #fff; outline-offset: 2px; }
        .user-avatar       { width: 2rem; height: 2rem; border-radius: 50%; background: #fff; color: var(--bs-success);
                             font-size: .8rem; font-weight: 700; letter-spacing: .02em;
                             display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .user-menu         { min-width: 15rem; }
    </style>
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

// Administrators also get a Users page, with a badge for accounts waiting for approval.
// Deactivated accounts (approved before, switched off later) are NOT pending, so they are not counted.
$pendingUsers = 0;
if (is_admin()) {
    $navItems['Users'] = ['modules/users/index.php', '/modules/users/'];
    try {
        $pendingUsers = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE is_active = FALSE AND approved_at IS NULL')->fetchColumn();
    } catch (PDOException $ex) {
        error_log($ex->getMessage());
    }
}

// Name pieces for the user menu: "Jimrey Dela Cruz" -> first name "Jimrey", initials "JC"
$nameParts = preg_split('/\s+/', trim($user['full_name']), -1, PREG_SPLIT_NO_EMPTY) ?: [];
$firstName = $nameParts[0] ?? $user['username'];
$initials  = mb_strtoupper(
    mb_substr($firstName, 0, 1) . (count($nameParts) > 1 ? mb_substr(end($nameParts), 0, 1) : '')
);
?>
<nav class="navbar app-nav navbar-expand-xl navbar-dark bg-success shadow-sm sticky-top">
    <div class="container-xxl">
        <a class="navbar-brand fw-semibold me-4" href="<?= BASE_URL ?>dashboard.php"><?= e(APP_NAME) ?></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false"
                aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto gap-xl-1">
                <?php foreach ($navItems as $label => [$href, $match]):
                    $isActive = strpos($path, $match) !== false; ?>
                    <li class="nav-item">
                        <a class="nav-link text-white <?= $isActive ? 'active fw-semibold' : 'opacity-75' ?>"
                           <?= $isActive ? 'aria-current="page"' : '' ?>
                           href="<?= BASE_URL . $href ?>"><?= e($label) ?><?php if ($label === 'Users' && $pendingUsers > 0): ?>
                            <span class="badge rounded-pill text-bg-danger ms-1" title="Accounts waiting for approval"><?= $pendingUsers ?><span class="visually-hidden"> accounts waiting for approval</span></span>
                        <?php endif; ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div class="dropdown mt-2 mt-xl-0">
                <button class="user-toggle dropdown-toggle" type="button"
                        data-bs-toggle="dropdown" aria-expanded="false" aria-label="Account menu">
                    <span class="user-avatar" aria-hidden="true"><?= e($initials) ?></span>
                    <span class="d-flex flex-column text-start lh-1">
                        <span class="fw-semibold small"><?= e($firstName) ?></span>
                        <span class="opacity-75" style="font-size: .7rem;"><?= e($user['role_name']) ?></span>
                    </span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end user-menu shadow">
                    <li class="px-3 py-2">
                        <div class="fw-semibold"><?= e($user['full_name']) ?></div>
                        <div class="text-muted small">@<?= e($user['username']) ?></div>
                        <span class="badge text-bg-success mt-2"><?= e($user['role_name']) ?></span>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="<?= BASE_URL ?>logout.php">Log out</a></li>
                </ul>
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
