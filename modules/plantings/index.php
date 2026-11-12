<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/_helpers.php';

$perPage  = 10;
$q        = trim($_GET['q'] ?? '');
$farmId   = get_int('farm_id');
$cropId   = get_int('crop_id');
$statusId = get_int('status_id');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo   = trim($_GET['date_to'] ?? '');
if (!valid_date_string($dateFrom)) {
    $dateFrom = '';
}
if (!valid_date_string($dateTo)) {
    $dateTo = '';
}

// Only these columns may be used for sorting (never trust ?sort= directly)
$sortMap = [
    'id'        => ['p.planting_id'],
    'crop'      => ['c.crop_name', 'c.variety'],
    'farm'      => ['fm.farm_name'],
    'planted'   => ['p.planting_date'],
    'expected'  => ['p.expected_harvest_date'],
    'area'      => ['p.area_planted'],
    'status'    => ['ps.status_id'],
    'harvests'  => ['harvest_count'],
];
$sort = $_GET['sort'] ?? 'planted';
if (!isset($sortMap[$sort])) {
    $sort = 'planted';
}
$dir      = (($_GET['dir'] ?? 'desc') === 'asc') ? 'asc' : 'desc';
$orderSql = implode(', ', array_map(fn($column) => "$column $dir NULLS LAST", $sortMap[$sort]));

$where  = [];
$params = [];

if ($q !== '') {
    $where[] = "(c.crop_name ILIKE :q1 OR c.variety ILIKE :q2 OR fm.farm_name ILIKE :q3
                 OR (fr.first_name || ' ' || fr.last_name) ILIKE :q4)";
    foreach ([':q1', ':q2', ':q3', ':q4'] as $name) {
        $params[$name] = '%' . like_escape($q) . '%';
    }
}
if ($farmId > 0) {
    $where[]            = 'p.farm_id = :farm_id';
    $params[':farm_id'] = $farmId;
}
if ($cropId > 0) {
    $where[]            = 'p.crop_id = :crop_id';
    $params[':crop_id'] = $cropId;
}
if ($statusId > 0) {
    $where[]              = 'p.status_id = :status_id';
    $params[':status_id'] = $statusId;
}
if ($dateFrom !== '') {
    $where[]              = 'p.planting_date >= :date_from';
    $params[':date_from'] = $dateFrom;
}
if ($dateTo !== '') {
    $where[]            = 'p.planting_date <= :date_to';
    $params[':date_to'] = $dateTo;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$fromSql = 'FROM planting p
            JOIN farm fm ON fm.farm_id = p.farm_id
            JOIN farmer fr ON fr.farmer_id = fm.farmer_id
            JOIN crop c ON c.crop_id = p.crop_id
            JOIN planting_status ps ON ps.status_id = p.status_id';

$plantings = [];
$farms     = [];
$crops     = [];
$statuses  = [];
$pager     = paginate(0, $perPage, 1);

try {
    $farms    = fetch_farm_options($pdo);
    $crops    = fetch_crop_options($pdo);
    $statuses = fetch_lookup($pdo, 'planting_status', 'status_id', 'status_name', true);

    $stmt = $pdo->prepare("SELECT COUNT(*) $fromSql $whereSql");
    $stmt->execute($params);
    $pager = paginate((int) $stmt->fetchColumn(), $perPage, get_int('page', 1));

    $sql = "SELECT p.planting_id, p.planting_date, p.expected_harvest_date, p.area_planted,
                   c.crop_name, c.variety, fm.farm_id, fm.farm_name,
                   fr.first_name, fr.last_name, ps.status_name,
                   (SELECT COUNT(*) FROM harvest h WHERE h.planting_id = p.planting_id) AS harvest_count
            $fromSql
            $whereSql
            ORDER BY $orderSql, p.planting_id DESC
            LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $pager['offset'], PDO::PARAM_INT);
    $stmt->execute();
    $plantings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $ex) {
    error_log($ex->getMessage());
    set_flash('danger', 'Could not load the plantings. Please try again.');
}

$pageTitle = 'Plantings';
require __DIR__ . '/../../includes/header.php';

$from = $pager['total'] === 0 ? 0 : $pager['offset'] + 1;
$to   = min($pager['offset'] + $perPage, $pager['total']);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Plantings</h1>
    <a href="add.php" class="btn btn-success">Add planting</a>
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
                <?php foreach ($farms as $id => $farm): ?>
                    <option value="<?= $id ?>" <?= $farmId === $id ? 'selected' : '' ?>><?= e($farm['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label for="crop_id" class="form-label mb-1">Crop</label>
            <select class="form-select" id="crop_id" name="crop_id">
                <option value="">All crops</option>
                <?php foreach ($crops as $id => $crop): ?>
                    <option value="<?= $id ?>" <?= $cropId === $id ? 'selected' : '' ?>><?= e($crop['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label for="status_id" class="form-label mb-1">Status</label>
            <select class="form-select" id="status_id" name="status_id">
                <option value="">All statuses</option>
                <?php foreach ($statuses as $id => $name): ?>
                    <option value="<?= $id ?>" <?= $statusId === $id ? 'selected' : '' ?>><?= e($name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label for="date_from" class="form-label mb-1">Planted from</label>
            <input type="date" class="form-control" id="date_from" name="date_from" value="<?= e($dateFrom) ?>">
        </div>
        <div class="col-md-3">
            <label for="date_to" class="form-label mb-1">Planted to</label>
            <input type="date" class="form-control" id="date_to" name="date_to" value="<?= e($dateTo) ?>">
        </div>
        <div class="col-md-3 d-flex gap-2">
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
                    <th><?= sort_link('Crop', 'crop', $sort, $dir) ?></th>
                    <th><?= sort_link('Farm', 'farm', $sort, $dir) ?></th>
                    <th><?= sort_link('Planted', 'planted', $sort, $dir) ?></th>
                    <th><?= sort_link('Expected harvest', 'expected', $sort, $dir) ?></th>
                    <th><?= sort_link('Area (ha)', 'area', $sort, $dir) ?></th>
                    <th><?= sort_link('Status', 'status', $sort, $dir) ?></th>
                    <th><?= sort_link('Harvests', 'harvests', $sort, $dir) ?></th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$plantings): ?>
                <tr><td colspan="9" class="text-center text-muted py-4">No plantings found.</td></tr>
            <?php endif; ?>
            <?php foreach ($plantings as $planting): ?>
                <tr>
                    <td><?= (int) $planting['planting_id'] ?></td>
                    <td>
                        <a href="view.php?id=<?= (int) $planting['planting_id'] ?>">
                            <?= e($planting['crop_name']) ?>
                        </a>
                        <?php if ($planting['variety'] !== null && $planting['variety'] !== ''): ?>
                            <div class="text-muted small"><?= e($planting['variety']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="<?= BASE_URL ?>modules/farms/view.php?id=<?= (int) $planting['farm_id'] ?>">
                            <?= e($planting['farm_name']) ?>
                        </a>
                        <div class="text-muted small"><?= e($planting['first_name'] . ' ' . $planting['last_name']) ?></div>
                    </td>
                    <td class="text-nowrap"><?= e(format_date($planting['planting_date'])) ?></td>
                    <td class="text-nowrap">
                        <?= $planting['expected_harvest_date'] === null ? display_value(null) : e(format_date($planting['expected_harvest_date'])) ?>
                    </td>
                    <td><?= $planting['area_planted'] === null ? display_value(null) : e(number_format((float) $planting['area_planted'], 2)) ?></td>
                    <td><?= status_badge($planting['status_name']) ?></td>
                    <td><?= (int) $planting['harvest_count'] ?></td>
                    <td class="text-end text-nowrap">
                        <a href="view.php?id=<?= (int) $planting['planting_id'] ?>" class="btn btn-sm btn-outline-secondary">View</a>
                        <a href="edit.php?id=<?= (int) $planting['planting_id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                        <?php if (is_admin()): ?>
                            <form method="post" action="delete.php" class="d-inline"
                                  onsubmit="return confirm('Delete this planting? This cannot be undone.');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="planting_id" value="<?= (int) $planting['planting_id'] ?>">
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
        Showing <?= $from ?>&ndash;<?= $to ?> of <?= $pager['total'] ?> planting<?= $pager['total'] === 1 ? '' : 's' ?>
    </div>
    <?= render_pagination($pager) ?>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
