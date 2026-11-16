<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/Harvest.php';
require_login();

$harvestModel = new Harvest($pdo);
$perPage      = 10;
$q            = trim($_GET['q'] ?? '');
$farmId       = get_int('farm_id');
$gradeId      = get_int('grade_id');
$remarkId     = get_int('remark_id');
$dateFrom     = trim($_GET['date_from'] ?? '');
$dateTo       = trim($_GET['date_to'] ?? '');
if (!valid_date_string($dateFrom)) {
    $dateFrom = '';
}
if (!valid_date_string($dateTo)) {
    $dateTo = '';
}

$sort = $_GET['sort'] ?? 'date';
if (!$harvestModel->isValidSort($sort)) {
    $sort = 'date';
}
$dir = (($_GET['dir'] ?? 'desc') === 'asc') ? 'asc' : 'desc';

$filters = [
    'q'         => $q,
    'farm_id'   => $farmId,
    'grade_id'  => $gradeId,
    'remark_id' => $remarkId,
    'date_from' => $dateFrom,
    'date_to'   => $dateTo,
];

$harvests   = [];
$farms      = [];
$grades     = [];
$remarks    = [];
$totalKg    = 0.0;
$totalValue = 0.0;
$pager      = paginate(0, $perPage, 1);

try {
    $farms   = $harvestModel->fetchFarmOptions();
    $grades  = $harvestModel->fetchGrades();
    $remarks = $harvestModel->fetchRemarks();

    $summary    = $harvestModel->summary($filters);
    $totalKg    = $summary['total_kg'];
    $totalValue = $summary['total_value'];

    $pager    = paginate($summary['count'], $perPage, get_int('page', 1));
    $harvests = $harvestModel->getAll($filters, $sort, $dir, $perPage, $pager['offset']);
} catch (PDOException $ex) {
    error_log($ex->getMessage());
    set_flash('danger', 'Could not load the harvests. Please try again.');
}

$pageTitle = 'Harvests';
require __DIR__ . '/../../includes/header.php';

$from = $pager['total'] === 0 ? 0 : $pager['offset'] + 1;
$to   = min($pager['offset'] + $perPage, $pager['total']);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Harvests</h1>
    <a href="add.php" class="btn btn-success">Add harvest</a>
</div>

<form method="get" action="" class="card card-body mb-3">
    <input type="hidden" name="sort" value="<?= e($sort) ?>">
    <input type="hidden" name="dir" value="<?= e($dir) ?>">
    <div class="row g-2 align-items-end">
        <div class="col-md-4">
            <label for="q" class="form-label mb-1">Search</label>
            <input type="text" class="form-control" id="q" name="q" value="<?= e($q) ?>"
                   placeholder="Crop, farm or owner">
        </div>
        <div class="col-md-4">
            <label for="farm_id" class="form-label mb-1">Farm</label>
            <select class="form-select" id="farm_id" name="farm_id">
                <option value="">All farms</option>
                <?php foreach ($farms as $id => $name): ?>
                    <option value="<?= $id ?>" <?= $farmId === $id ? 'selected' : '' ?>><?= e($name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label for="grade_id" class="form-label mb-1">Grade</label>
            <select class="form-select" id="grade_id" name="grade_id">
                <option value="">All</option>
                <?php foreach ($grades as $id => $name): ?>
                    <option value="<?= $id ?>" <?= $gradeId === $id ? 'selected' : '' ?>><?= e($name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label for="remark_id" class="form-label mb-1">Remark</label>
            <select class="form-select" id="remark_id" name="remark_id">
                <option value="">All</option>
                <?php foreach ($remarks as $id => $name): ?>
                    <option value="<?= $id ?>" <?= $remarkId === $id ? 'selected' : '' ?>><?= e($name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label for="date_from" class="form-label mb-1">Harvested from</label>
            <input type="date" class="form-control" id="date_from" name="date_from" value="<?= e($dateFrom) ?>">
        </div>
        <div class="col-md-3">
            <label for="date_to" class="form-label mb-1">Harvested to</label>
            <input type="date" class="form-control" id="date_to" name="date_to" value="<?= e($dateTo) ?>">
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-success flex-fill">Filter</button>
            <a href="index.php" class="btn btn-outline-secondary">Reset</a>
        </div>
    </div>
</form>

<div class="alert alert-light border d-flex flex-wrap gap-4 py-2">
    <div>Total quantity: <strong><?= e(number_format((float) $totalKg, 2)) ?> kg</strong></div>
    <div>Estimated value: <strong>&#8369;<?= e(number_format((float) $totalValue, 2)) ?></strong></div>
    <div class="text-muted small align-self-center">Based on the current search and filters</div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th><?= sort_link('ID', 'id', $sort, $dir) ?></th>
                    <th><?= sort_link('Date', 'date', $sort, $dir) ?></th>
                    <th><?= sort_link('Crop', 'crop', $sort, $dir) ?></th>
                    <th><?= sort_link('Farm', 'farm', $sort, $dir) ?></th>
                    <th><?= sort_link('Quantity (kg)', 'quantity', $sort, $dir) ?></th>
                    <th><?= sort_link('Price/kg', 'price', $sort, $dir) ?></th>
                    <th><?= sort_link('Value', 'value', $sort, $dir) ?></th>
                    <th><?= sort_link('Grade', 'grade', $sort, $dir) ?></th>
                    <th><?= sort_link('Remark', 'remark', $sort, $dir) ?></th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$harvests): ?>
                <tr><td colspan="10" class="text-center text-muted py-4">No harvests found.</td></tr>
            <?php endif; ?>
            <?php foreach ($harvests as $harvest): ?>
                <tr>
                    <td><?= (int) $harvest['harvest_id'] ?></td>
                    <td class="text-nowrap">
                        <a href="view.php?id=<?= (int) $harvest['harvest_id'] ?>"><?= e(format_date($harvest['harvest_date'])) ?></a>
                    </td>
                    <td>
                        <?= e($harvest['crop_name']) ?>
                        <?php if ($harvest['variety'] !== null && $harvest['variety'] !== ''): ?>
                            <div class="text-muted small"><?= e($harvest['variety']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="<?= BASE_URL ?>modules/farms/view.php?id=<?= (int) $harvest['farm_id'] ?>"><?= e($harvest['farm_name']) ?></a>
                    </td>
                    <td><?= e(number_format((float) $harvest['quantity_kg'], 2)) ?></td>
                    <td><?= $harvest['selling_price_per_kg'] === null ? display_value(null) : '&#8369;' . e(number_format((float) $harvest['selling_price_per_kg'], 2)) ?></td>
                    <td><?= $harvest['total_value'] === null ? display_value(null) : '&#8369;' . e(number_format((float) $harvest['total_value'], 2)) ?></td>
                    <td><?= display_value($harvest['grade_name']) ?></td>
                    <td><?= e($harvest['remark_description']) ?></td>
                    <td class="text-end text-nowrap">
                        <a href="view.php?id=<?= (int) $harvest['harvest_id'] ?>" class="btn btn-sm btn-outline-secondary">View</a>
                        <a href="edit.php?id=<?= (int) $harvest['harvest_id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                        <?php if (is_admin()): ?>
                            <form method="post" action="delete.php" class="d-inline"
                                  onsubmit="return confirm('Delete this harvest record? This cannot be undone.');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="harvest_id" value="<?= (int) $harvest['harvest_id'] ?>">
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
        Showing <?= $from ?>&ndash;<?= $to ?> of <?= $pager['total'] ?> harvest<?= $pager['total'] === 1 ? '' : 's' ?>
    </div>
    <?= render_pagination($pager) ?>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
