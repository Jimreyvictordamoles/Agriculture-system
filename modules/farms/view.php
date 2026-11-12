<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$id) {
    set_flash('danger', 'Farm not found.');
    redirect('modules/farms/index.php');
}

$plantings = [];
try {
    $stmt = $pdo->prepare(
        'SELECT fm.farm_id, fm.farm_name, fm.location, fm.area_hectares, fm.created_at, fm.updated_at,
                fr.farmer_id, fr.first_name, fr.last_name,
                st.soil_type_name, ir.irrigation_name
         FROM farm fm
         JOIN farmer fr ON fr.farmer_id = fm.farmer_id
         LEFT JOIN soil_type st ON st.soil_type_id = fm.soil_type_id
         LEFT JOIN irrigation_type ir ON ir.irrigation_id = fm.irrigation_id
         WHERE fm.farm_id = :id'
    );
    $stmt->execute([':id' => $id]);
    $farm = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($farm) {
        $stmt = $pdo->prepare(
            'SELECT p.planting_id, c.crop_name, c.variety, p.planting_date, ps.status_name
             FROM planting p
             JOIN crop c ON c.crop_id = p.crop_id
             JOIN planting_status ps ON ps.status_id = p.status_id
             WHERE p.farm_id = :id
             ORDER BY p.planting_date DESC, p.planting_id DESC'
        );
        $stmt->execute([':id' => $id]);
        $plantings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $ex) {
    error_log($ex->getMessage());
    $farm = false;
}

if (!$farm) {
    set_flash('danger', 'Farm not found.');
    redirect('modules/farms/index.php');
}

$pageTitle = $farm['farm_name'];
require __DIR__ . '/../../includes/header.php';

// "YYYY-MM-DD" to "Nov 1, 2026"
$formatDate = function (?string $value): string {
    $date = $value ? DateTime::createFromFormat('!Y-m-d', $value) : false;
    return $date ? $date->format('M j, Y') : '';
};
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Farm details</h1>
    <a href="index.php" class="btn btn-outline-secondary">Back to list</a>
</div>

<div class="card">
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-4">Farm name</dt>
            <dd class="col-sm-8"><?= e($farm['farm_name']) ?></dd>

            <dt class="col-sm-4">Owner</dt>
            <dd class="col-sm-8">
                <a href="<?= BASE_URL ?>modules/farmers/view.php?id=<?= (int) $farm['farmer_id'] ?>">
                    <?= e($farm['first_name'] . ' ' . $farm['last_name']) ?>
                </a>
            </dd>

            <dt class="col-sm-4">Location</dt>
            <dd class="col-sm-8"><?= e($farm['location']) ?></dd>

            <dt class="col-sm-4">Area</dt>
            <dd class="col-sm-8">
                <?= $farm['area_hectares'] === null ? display_value(null) : e(number_format((float) $farm['area_hectares'], 2)) . ' hectares' ?>
            </dd>

            <dt class="col-sm-4">Soil type</dt>
            <dd class="col-sm-8"><?= display_value($farm['soil_type_name']) ?></dd>

            <dt class="col-sm-4">Irrigation</dt>
            <dd class="col-sm-8"><?= display_value($farm['irrigation_name']) ?></dd>

            <dt class="col-sm-4">Created</dt>
            <dd class="col-sm-8"><?= e(format_datetime($farm['created_at'])) ?></dd>

            <dt class="col-sm-4">Last updated</dt>
            <dd class="col-sm-8"><?= e(format_datetime($farm['updated_at'])) ?></dd>
        </dl>
    </div>
</div>

<h2 class="h5 mt-4 mb-2">Plantings (<?= count($plantings) ?>)</h2>
<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
                <tr><th>Crop</th><th>Variety</th><th>Planted</th><th>Status</th></tr>
            </thead>
            <tbody>
            <?php if (!$plantings): ?>
                <tr><td colspan="4" class="text-center text-muted py-3">No plantings recorded for this farm yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($plantings as $planting): ?>
                <tr>
                    <td>
                        <a href="<?= BASE_URL ?>modules/plantings/view.php?id=<?= (int) $planting['planting_id'] ?>">
                            <?= e($planting['crop_name']) ?>
                        </a>
                    </td>
                    <td><?= display_value($planting['variety']) ?></td>
                    <td><?= e($formatDate($planting['planting_date'])) ?></td>
                    <td><?= e($planting['status_name']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3 d-flex gap-2">
    <a href="edit.php?id=<?= (int) $farm['farm_id'] ?>" class="btn btn-primary">Edit</a>
    <?php if (is_admin()): ?>
        <form method="post" action="delete.php"
              onsubmit="return confirm('Delete this farm? This cannot be undone.');">
            <?= csrf_field() ?>
            <input type="hidden" name="farm_id" value="<?= (int) $farm['farm_id'] ?>">
            <button type="submit" class="btn btn-outline-danger">Delete</button>
        </form>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
