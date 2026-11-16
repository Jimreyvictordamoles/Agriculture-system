<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/Crop.php';
require_login();

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$id) {
    set_flash('danger', 'Crop not found.');
    redirect('modules/crops/index.php');
}

$cropModel = new Crop($pdo);

try {
    $crop = $cropModel->find($id);
} catch (PDOException $ex) {
    error_log($ex->getMessage());
    $crop = null;
}

if (!$crop) {
    set_flash('danger', 'Crop not found.');
    redirect('modules/crops/index.php');
}

$pageTitle = $crop['crop_name'];
require __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Crop details</h1>
    <a href="index.php" class="btn btn-outline-secondary">Back to list</a>
</div>

<div class="card">
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-4">Crop name</dt>
            <dd class="col-sm-8"><?= e($crop['crop_name']) ?></dd>

            <dt class="col-sm-4">Variety</dt>
            <dd class="col-sm-8"><?= display_value($crop['variety']) ?></dd>

            <dt class="col-sm-4">Category</dt>
            <dd class="col-sm-8"><?= display_value($crop['category_name']) ?></dd>

            <dt class="col-sm-4">Recommended season</dt>
            <dd class="col-sm-8"><?= display_value($crop['season_name']) ?></dd>

            <dt class="col-sm-4">Growing period</dt>
            <dd class="col-sm-8">
                <?= $crop['growing_period_days'] === null ? display_value(null) : (int) $crop['growing_period_days'] . ' days' ?>
            </dd>

            <dt class="col-sm-4">Used in plantings</dt>
            <dd class="col-sm-8"><?= (int) $crop['planting_count'] ?></dd>

            <dt class="col-sm-4">Created</dt>
            <dd class="col-sm-8"><?= e(format_datetime($crop['created_at'])) ?></dd>

            <dt class="col-sm-4">Last updated</dt>
            <dd class="col-sm-8"><?= e(format_datetime($crop['updated_at'])) ?></dd>
        </dl>
    </div>
</div>

<div class="mt-3 d-flex gap-2">
    <a href="edit.php?id=<?= (int) $crop['crop_id'] ?>" class="btn btn-primary">Edit</a>
    <?php if (is_admin()): ?>
        <form method="post" action="delete.php"
              onsubmit="return confirm('Delete this crop? This cannot be undone.');">
            <?= csrf_field() ?>
            <input type="hidden" name="crop_id" value="<?= (int) $crop['crop_id'] ?>">
            <button type="submit" class="btn btn-outline-danger">Delete</button>
        </form>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
