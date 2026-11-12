<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/_helpers.php';

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$id) {
    set_flash('danger', 'Farmer not found.');
    redirect('modules/farmers/index.php');
}

$farms = [];
try {
    $stmt = $pdo->prepare(
        'SELECT farmer_id, first_name, last_name, contact_number, address,
                date_registered, created_at, updated_at
         FROM farmer WHERE farmer_id = :id'
    );
    $stmt->execute([':id' => $id]);
    $farmer = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($farmer) {
        $stmt = $pdo->prepare(
            'SELECT farm_id, farm_name, location, area_hectares
             FROM farm WHERE farmer_id = :id ORDER BY farm_name'
        );
        $stmt->execute([':id' => $id]);
        $farms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $ex) {
    error_log($ex->getMessage());
    $farmer = false;
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
                <tr>
                    <td><a href="<?= BASE_URL ?>modules/farms/view.php?id=<?= (int) $farm['farm_id'] ?>"><?= e($farm['farm_name']) ?></a></td>
                    <td><?= e($farm['location']) ?></td>
                    <td><?= $farm['area_hectares'] === null ? display_value(null) : e(number_format((float) $farm['area_hectares'], 2)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3 d-flex gap-2">
    <a href="edit.php?id=<?= (int) $farmer['farmer_id'] ?>" class="btn btn-primary">Edit</a>
    <?php if (is_admin()): ?>
        <form method="post" action="delete.php"
              onsubmit="return confirm('Delete this farmer? This cannot be undone.');">
            <?= csrf_field() ?>
            <input type="hidden" name="farmer_id" value="<?= (int) $farmer['farmer_id'] ?>">
            <button type="submit" class="btn btn-outline-danger">Delete</button>
        </form>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
