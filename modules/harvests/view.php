<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/_helpers.php';

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$id) {
    set_flash('danger', 'Harvest not found.');
    redirect('modules/harvests/index.php');
}

try {
    $stmt = $pdo->prepare(
        'SELECT h.harvest_id, h.harvest_date, h.quantity_kg, h.selling_price_per_kg,
                (h.quantity_kg * h.selling_price_per_kg) AS total_value,
                h.created_at, h.updated_at,
                p.planting_id, p.planting_date,
                c.crop_id, c.crop_name, c.variety,
                fm.farm_id, fm.farm_name, fr.farmer_id, fr.first_name, fr.last_name,
                qg.grade_name, hr.remark_description
         FROM harvest h
         JOIN planting p ON p.planting_id = h.planting_id
         JOIN farm fm ON fm.farm_id = p.farm_id
         JOIN farmer fr ON fr.farmer_id = fm.farmer_id
         JOIN crop c ON c.crop_id = p.crop_id
         JOIN harvest_remark hr ON hr.remark_id = h.remark_id
         LEFT JOIN quality_grade qg ON qg.grade_id = h.grade_id
         WHERE h.harvest_id = :id'
    );
    $stmt->execute([':id' => $id]);
    $harvest = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $ex) {
    error_log($ex->getMessage());
    $harvest = false;
}

if (!$harvest) {
    set_flash('danger', 'Harvest not found.');
    redirect('modules/harvests/index.php');
}

$cropLabel = $harvest['crop_name']
    . ($harvest['variety'] !== null && $harvest['variety'] !== '' ? ' (' . $harvest['variety'] . ')' : '');

$pageTitle = $cropLabel . ' harvest';
require __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Harvest details</h1>
    <a href="index.php" class="btn btn-outline-secondary">Back to list</a>
</div>

<div class="card">
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-4">Harvest date</dt>
            <dd class="col-sm-8"><?= e(format_date($harvest['harvest_date'])) ?></dd>

            <dt class="col-sm-4">Crop</dt>
            <dd class="col-sm-8">
                <a href="<?= BASE_URL ?>modules/crops/view.php?id=<?= (int) $harvest['crop_id'] ?>"><?= e($cropLabel) ?></a>
            </dd>

            <dt class="col-sm-4">Farm</dt>
            <dd class="col-sm-8">
                <a href="<?= BASE_URL ?>modules/farms/view.php?id=<?= (int) $harvest['farm_id'] ?>"><?= e($harvest['farm_name']) ?></a>
            </dd>

            <dt class="col-sm-4">Owner</dt>
            <dd class="col-sm-8">
                <a href="<?= BASE_URL ?>modules/farmers/view.php?id=<?= (int) $harvest['farmer_id'] ?>">
                    <?= e($harvest['first_name'] . ' ' . $harvest['last_name']) ?>
                </a>
            </dd>

            <dt class="col-sm-4">Planting</dt>
            <dd class="col-sm-8">
                <a href="<?= BASE_URL ?>modules/plantings/view.php?id=<?= (int) $harvest['planting_id'] ?>">
                    Planted <?= e(format_date($harvest['planting_date'])) ?>
                </a>
            </dd>

            <dt class="col-sm-4">Quantity</dt>
            <dd class="col-sm-8"><?= e(number_format((float) $harvest['quantity_kg'], 2)) ?> kg</dd>

            <dt class="col-sm-4">Selling price per kg</dt>
            <dd class="col-sm-8">
                <?= $harvest['selling_price_per_kg'] === null ? display_value(null) : '&#8369;' . e(number_format((float) $harvest['selling_price_per_kg'], 2)) ?>
            </dd>

            <dt class="col-sm-4">Estimated value</dt>
            <dd class="col-sm-8">
                <?= $harvest['total_value'] === null ? display_value(null) : '&#8369;' . e(number_format((float) $harvest['total_value'], 2)) ?>
            </dd>

            <dt class="col-sm-4">Quality grade</dt>
            <dd class="col-sm-8"><?= display_value($harvest['grade_name']) ?></dd>

            <dt class="col-sm-4">Remark</dt>
            <dd class="col-sm-8"><?= e($harvest['remark_description']) ?></dd>

            <dt class="col-sm-4">Created</dt>
            <dd class="col-sm-8"><?= e(format_datetime($harvest['created_at'])) ?></dd>

            <dt class="col-sm-4">Last updated</dt>
            <dd class="col-sm-8"><?= e(format_datetime($harvest['updated_at'])) ?></dd>
        </dl>
    </div>
</div>

<div class="mt-3 d-flex gap-2">
    <a href="edit.php?id=<?= (int) $harvest['harvest_id'] ?>" class="btn btn-primary">Edit</a>
    <?php if (is_admin()): ?>
        <form method="post" action="delete.php"
              onsubmit="return confirm('Delete this harvest record? This cannot be undone.');">
            <?= csrf_field() ?>
            <input type="hidden" name="harvest_id" value="<?= (int) $harvest['harvest_id'] ?>">
            <button type="submit" class="btn btn-outline-danger">Delete</button>
        </form>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
