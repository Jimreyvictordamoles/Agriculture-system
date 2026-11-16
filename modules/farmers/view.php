<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/Farmer.php';
require_login();

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$id) {
    set_flash('danger', 'Farmer not found.');
    redirect('modules/farmers/index.php');
}

$farmerModel = new Farmer($pdo);
$farms       = [];

try {
    $farmer = $farmerModel->find($id);

    if ($farmer) {
        $farms = $farmerModel->getFarms($id);
    }
} catch (PDOException $ex) {
    error_log($ex->getMessage());
    $farmer = null;
}

if (!$farmer) {
    set_flash('danger', 'Farmer not found.');
    redirect('modules/farmers/index.php');
}

$pageTitle = $farmer['first_name'] . ' ' . $farmer['last_name'];
require __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Farmer details</h1>
    <a href="index.php" class="btn btn-outline-secondary">Back to list</a>
</div>

<div class="card">
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-4">Name</dt>
            <dd class="col-sm-8"><?= e($farmer['first_name'] . ' ' . $farmer['last_name']) ?></dd>

            <dt class="col-sm-4">Contact number</dt>
            <dd class="col-sm-8"><?= display_value($farmer['contact_number']) ?></dd>

            <dt class="col-sm-4">Address</dt>
            <dd class="col-sm-8"><?= display_value($farmer['address']) ?></dd>

            <dt class="col-sm-4">Date registered</dt>
            <dd class="col-sm-8"><?= e(format_date($farmer['date_registered'])) ?></dd>

            <dt class="col-sm-4">Created</dt>
            <dd class="col-sm-8"><?= e(format_datetime($farmer['created_at'])) ?></dd>

            <dt class="col-sm-4">Last updated</dt>
            <dd class="col-sm-8"><?= e(format_datetime($farmer['updated_at'])) ?></dd>
        </dl>
    </div>
</div>

<h2 class="h5 mt-4 mb-2">Farms (<?= count($farms) ?>)</h2>
<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
                <tr><th>Farm</th><th>Location</th><th>Area (ha)</th></tr>
            </thead>
            <tbody>
            <?php if (!$farms): ?>
                <tr><td colspan="3" class="text-center text-muted py-3">This farmer has no farms yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($farms as $farm): ?>
                