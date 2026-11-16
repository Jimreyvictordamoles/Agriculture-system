<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/Farm.php';
require_login();

$farmModel    = new Farm($pdo);
$perPage      = 10;
$q            = trim($_GET['q'] ?? '');
$farmerId     = get_int('farmer_id');
$soilTypeId   = get_int('soil_type_id');
$irrigationId = get_int('irrigation_id');

$sort = $_GET['sort'] ?? 'farm';
if (!$farmModel->isValidSort($sort)) {
    $sort = 'farm';
}
$dir = (($_GET['dir'] ?? 'asc') === 'desc') ? 'desc' : 'asc';

$filters = [
    'q'             => $q,
    'farmer_id'     => $farmerId,
    'soil_type_id'  => $soilTypeId,
    'irrigation_id' => $irrigationId,
];

$farms           = [];
$farmers         = [];
$soilTypes       = [];
$irrigationTypes = [];
$pager           = paginate(0, $perPage, 1);

try {
    $farmers         = $farmModel->fetchFarmerOptions();
    $soilTypes       = $farmModel->fetchSoilTypes();
    $irrigationTypes = $farmModel->fetchIrrigationTypes();

    $pager = paginate($farmModel->count($filters), $perPage, get_int('page', 1));
    $farms = $farmModel->getAll($filters, $sort, $dir, $perPage, $pager['offset']);
} catch (PDOException $ex) {
    error_log($ex->getMessage());
    set_flash('danger', 'Could not load the farms. Please try again.');
}

$pageTitle = 'Farms';
require __DIR__ . '/../../includes/header.php';

$from = $pager['total'] === 0 ? 0 : $pager['offset'] + 1;
$to   = min($pager['offset'] + $perPage, $pager['total']);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Farms</h1>
    <a href="add.php" class="btn btn-success">Add farm</a>
</div>

<form method="get" action="" class="card card-body mb-3">
    <input type="hidden" name="sort" value="<?= e($sort) ?>">
    <input type="hidden" name="dir" value="<?= e($dir) ?>">
    <div class="row g-2 align-items-end">
        <div class="col-md-3">
            <label for="q" class="form-label mb-1">Search</label>
            <input type="text" class="form-control" id="q" name="q" value="<?= e($q) ?>"
                   placeholder="Farm, location or owner">
        </div>
        <div class="col-md-3">
            <label for="farmer_id" class="form-label mb-1">Owner</label>
            <select class="form-select" id="farmer_id" name="farmer_id">
                <option value="">All farmers</option>
                <?php foreach ($farmers as $id => $name): ?>
                    <option value="<?= $id ?>" <?= $farmerId === $id ? 'selected' : '' ?>><?= e($name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label for="soil_type_id" class="form-label mb-1">Soil type</label>
            <select class="form-select" id="soil_type_id" name="soil_type_id">
                <option value="">All soil types</option>
                <?php foreach ($soilTypes as $id => $name): ?>
                    <option value="<?= $id ?>" <?= $soilTypeId === $id ? 'selected' : '' ?>><?= e($name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label for="irrigation_id" class="form-label mb-1">Irrigation</label>
            <select class="form-select" id="irrigation_id" name="irrigation_id">
                <option value="">All types</option>
                <?php foreach ($irrigationTypes as $id => $name): ?>
                    <option value="<?= $id ?>" <?= $irrigationId === $id ? 'selected' : '' ?>><?= e($name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-success flex-fill">Filter</button>
            <a href="index.php" class="btn btn-outline-secondary">Reset</a>
        </div>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th><?= sort_link('ID', 'id', $sort, $dir) ?></th>
                    <th><?= sort_link('Farm', 'farm', $sort, $dir) ?></th>
                    <th><?= sort_link('Owner', 'owner', $sort, $dir) ?></th>
                    <th><?= sort_link('Location', 'location', $sort, $dir) ?></th>
                    <th><?= sort_link('Area (ha)', 'area', $sort, $dir) ?></th>
                    <th><?= sort_link('Soil', 'soil', $sort, $dir) ?></th>
                    <th><?= sort_link('Irrigation', 'irrigation', $sort, $dir) ?></th>
                    <th><?= sort_link('Plantings', 'plantings', $sort, $dir) ?></th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$farms): ?>
                <tr><td colspan="9" class="text-center text-muted py-4">No farms found.</td></tr>
            <?php endif; ?>
            <?php foreach ($farms as $farm): ?>
                <tr>
                    <td><?= (int) $farm['farm_id'] ?></td>
                    <td><a href="view.php?id=<?= (int) $farm['farm_id'] ?>"><?= e($farm['farm_name']) ?></a></td>
                    <td>
                        <a href="<?= BASE_URL ?>modules/farmers/view.php?id=<?= (int) $farm['farmer_id'] ?>">
                            <?= e($farm['first_name'] . ' ' . $farm['last_name']) ?>
                        </a>
                    </td>
                    <td class="text-break"><?= e($farm['location']) ?></td>
                    <td><?= $farm['area_hectares'] === null ? display_value(null) : e(number_format((float) $farm['area_hectares'], 2)) ?></td>
                    <td><?= display_value($farm['soil_type_name']) ?></td>
                    <td><?= display_value($farm['irrigation_name']) ?></td>
                    <td><?= (int) $farm['planting_count'] ?></td>
                    <td class="text-end text-nowrap">
                        <a href="view.php?id=<?= (int) $farm['farm_id'] ?>" class="btn btn-sm btn-outline-secondary">View</a>
                        <a href="edit.php?id=<?= (int) $farm['farm_id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                        <?php if (is_admin()): ?>
                            <form method="post" action="delete.php" class="d-inline"
                                  onsubmit="return confirm('Delete this farm? This cannot be undone.');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="farm_id" value="<?= (int) $farm['farm_id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-2 mt-3">
    <div class="text-muted small">
        Showing <?= $from ?>&ndash;<?= $to ?> of <?= $pager['total'] ?> farm<?= $pager['total'] === 1 ? '' : 's' ?>
    </div>
    <?= render_pagination($pager) ?>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
