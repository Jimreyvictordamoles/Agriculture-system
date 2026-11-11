<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$pageTitle = 'Dashboard';
require __DIR__ . '/includes/header.php';
?>
<h1 class="h3 mb-3">Dashboard</h1>
<p>
    Welcome, <strong><?= e($user['full_name']) ?></strong>.
    You are logged in as <span class="badge bg-success"><?= e($user['role_name']) ?></span>.
</p>

<?php if (is_admin()): ?>
    <div class="alert alert-info">Administrator view: you will be able to delete records and manage users.</div>
<?php else: ?>
    <div class="alert alert-secondary">Staff view: you can add and edit records.</div>
<?php endif; ?>

<p class="text-muted">Summary cards and charts will be added here later.</p>
<?php require __DIR__ . '/includes/footer.php'; ?>
