<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/_helpers.php';

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$id) {
    set_flash('danger', 'Planting not found.');
    redirect('modules/plantings/index.php');
}

$harvests = [];
try {
    $stmt = $pdo->prepare(
        'SELECT p.planting_id, p.planting_date, p.expected_harvest_date, p.area_planted,
                p.seed_quantity, p.created_at, p.updated_at,
                c.crop_id, c.crop_name, c.variety,
                fm.farm_id, fm.farm_name, fr.farmer_id, fr.first_name, fr.last_name,
                ps.status_name
         FROM planting p
         JOIN farm fm ON fm.farm_id = p.farm_id
         JOIN farmer fr ON fr.farmer_id = fm.farmer_id
         JOIN crop c ON c.crop_id = p.crop_id
         JOIN planting_status ps ON ps.status_id = p.status_id
         WHERE p.planting_id = :id'
    );
    $stmt->execute([':id' => $id]);
    $planting = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($planting) {
        $stmt = $pdo->prepare(
            'SELECT h.harvest_id, h.harvest_date, h.quantity_kg, h.selling_price_per_kg,
                    qg.grade_name, hr.remark_description
             FROM harvest h
             JOIN harvest_remark hr ON hr.remark_id = h.remark_id
             LEFT JOIN quality_grade qg ON qg.grade_id = h.grade_id
             WHERE h.planting_id = :id
             ORDER BY h.harvest_date DESC, h.harvest_id DESC'
        );
        $stmt->execute([':id' => $id]);
        $harvests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $ex) {
    error_log($ex->getMessage());
    $planting = false;
}

if (!$planting) {
    set_flash('danger', 'Planting not found.');
    redirect('modules/plantings/index.php');
}

$totalKg = 0.0;
foreach ($harvests as $harvest) {
    $totalKg += (float) $harvest['quantity_kg'];
}

$cropLabel = $planting['crop_name']
    . ($planting['variety'] !== null && $planting['variety'] !== '' ? ' (' . $planting['variety'] . ')' : '');

$pageTitle = $cropLabel . ' planting';
require __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Planting details</h1>
    <a href="index.php" class="btn btn-outline-secondary">Back to list</a>
</div>

<div class="card">
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-4">Crop</dt>
            <dd class="col-sm-8">
                <a href="<?= BASE_URL ?>modules/crops/view.php?id=<?= (int) $planting['crop_id'] ?>"><?= e($cropLabel) ?></a>
            </dd>

            <dt class="col-sm-4">Farm</dt>
            <dd class="col-sm-8">
                <a href="<?= BASE_URL ?>modules/farms/view.php?id=<?= (int) $planting['farm_id'] ?>"><?= e($planting['farm_name']) ?></a>
            </dd>

            <dt class="col-sm-4">Owner</dt>
            <dd class="col-sm-8">
                <a href="<?= BASE_URL ?>modules/farmers/view.php?id=<?= (int) $planting['farmer_id'] ?>">
                    <?= e($planting['first_name'] . ' ' . $planting['last_name']) ?>
                </a>
            </dd>

            <dt class="col-sm-4">Status</dt>
            <dd class="col-sm-8"><?= status_badge($planting['status_name']) ?></dd>

            <dt class="col-sm-4">Planting date</dt>
            <dd class="col-sm-8"><?= e(format_date($planting['planting_date'])) ?></dd>

            <dt class="col-sm-4">Expected harvest date</dt>
            <dd class="col-sm-8">
                <?= $planting['expected_harvest_date'] === null ? display_value(null) : e(format_date($planting['expected_harvest_date'])) ?>
            </dd>

            <dt class="col-sm-4">Area planted</dt>
            <dd class="col-sm-8">
                <?= $planting['area_planted'] === null ? display_value(null) : e(number_format((float) $planting['area_planted'], 2)) . ' hectares' ?>
            </dd>

            <dt class="col-sm-4">Seed quantity</dt>
            <dd class="col-sm-8">
                <?= $planting['seed_quantity'] === null ? display_value(null) : e(number_format((float) $planting['seed_quantity'], 2)) . ' kg' ?>
            </dd>

            <dt class="col-sm-4">Created</dt>
            <dd class="col-sm-8"><?= e(format_datetime($planting['created_at'])) ?></dd>

            <dt class="col-sm-4">Last updated</dt>
            <dd class="col-sm-8"><?= e(format_datetime($planting['updated_at'])) ?></dd>
        </dl>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mt-4 mb-2">
    <h2 class="h5 mb-0">Harvests (<?= count($harvests) ?>) &middot; total <?= e(number_format($totalKg, 2)) ?> kg</h2>
    <a href="<?= BASE_URL ?>modules/harvests/add.php?planting_id=<?= (int) $planting['planting_id'] ?>"
       class="btn btn-sm btn-success">Record harvest</a>
</div>
<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
                <tr><th>Date</th><th>Quantity (kg)</th><th>Grade</th><th>Remark</th><th>Price per kg</th></tr>
            </thead>
            <tbody>
            <?php if (!$harvests): ?>
                <tr><td colspan="5" class="text-center text-muted py-3">No harvests recorded yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($harvests as $harvest): ?>
                <tr>
                    <td>
                        <a href="<?= BASE_URL ?>modules/harvests/view.php?id=<?= (int) $harvest['harvest_id'] ?>">
                            <?= e(format_date($harvest['harvest_date'])) ?>
                        </a>
                    </td>
                    <td><?= e(number_format((float) $harvest['quantity_kg'], 2)) ?></td>
                    <td><?= display_value($harvest['grade_name']) ?></td>
                    <td><?= e($harvest['remark_description']) ?></td>
                    <td><?= $harvest['selling_price_per_kg'] === null ? display_value(null) : e(number_format((float) $harvest['selling_price_per_kg'], 2)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3 d-flex gap-2">
    <a href="edit.php?id=<?= (int) $planting['planting_id'] ?>" class="btn btn-primary">Edit</a>
    <?php if (is_admin()): ?>
        <form method="post" action="delete.php"
              onsubmit="return confirm('Delete this planting? This cannot be undone.');">
            <?= csrf_field() ?>
            <input type="hidden" name="planting_id" value="<?= (int) $planting['planting_id'] ?>">
            <button type="submit" class="btn btn-outline-danger">Delete</button>
        </form>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
