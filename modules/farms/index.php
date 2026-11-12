<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/_helpers.php';

$perPage      = 10;
$q            = trim($_GET['q'] ?? '');
$farmerId     = get_int('farmer_id');
$soilTypeId   = get_int('soil_type_id');
$irrigationId = get_int('irrigation_id');

// Only these columns may be used for sorting (never trust ?sort= directly)
$sortMap = [
    'id'          => ['fm.farm_id'],
    'farm'        => ['fm.farm_name'],
    'owner'       => ['fr.last_name', 'fr.first_name'],
    'location'    => ['fm.location'],
    'area'        => ['fm.area_hectares'],
    'soil'        => ['st.soil_type_name'],
    'irrigation'  => ['ir.irrigation_name'],
    'plantings'   => ['planting_count'],
];
$sort = $_GET['sort'] ?? 'farm';
if (!isset($sortMap[$sort])) {
    $sort = 'farm';
}
$dir      = (($_GET['dir'] ?? 'asc') === 'desc') ? 'desc' : 'asc';
$orderSql = implode(', ', array_map(fn($column) => "$column $dir NULLS LAST", $sortMap[$sort]));

$where  = [];
$params = [];

if ($q !== '') {
    $where[] = "(fm.farm_name ILIKE :q1 OR fm.location ILIKE :q2
                 OR (fr.first_name || ' ' || fr.last_name) ILIKE :q3)";
    foreach ([':q1', ':q2', ':q3'] as $name) {
        $params[$name] = '%' . like_escape($q) . '%';
    }
}
if ($farmerId > 0) {
    $where[]              = 'fm.farmer_id = :farmer_id';
    $params[':farmer_id'] = $farmerId;
}
if ($soilTypeId > 0) {
    $where[]                = 'fm.soil_type_id = :soil_type_id';
    $params[':soil_type_id'] = $soilTypeId;
}
if ($irrigationId > 0) {
    $where[]                 = 'fm.irrigation_id = :irrigation_id';
    $params[':irrigation_id'] = $irrigationId;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$farms           = [];
$farmers         = [];
$soilTypes       = [];
$irrigationTypes = [];
$pager           = paginate(0, $perPage, 1);

try {
    $farmers         = fetch_farmer_options($pdo);
    $soilTypes       = fetch_lookup($pdo, 'soil_type', 'soil_type_id', 'soil_type_name');
    $irrigationTypes = fetch_lookup($pdo, 'irrigation_type', 'irrigation_id', 'irrigation_name');

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM farm fm JOIN farmer fr ON fr.farmer_id = fm.farmer_id $whereSql"
    );
    $stmt->execute($params);
    $pager = paginate((int) $stmt->fetchColumn(), $perPage, get_int('page', 1));

    $sql = "SELECT fm.farm_id, fm.farm_name, fm.location, fm.area_hectares,
                   fr.farmer_id, fr.first_name, fr.last_name,
                   st.soil_type_name, ir.irrigation_name,
                   (SELECT COUNT(*) FROM planting p WHERE p.farm_id = fm.farm_id) AS planting_count
            FROM farm fm
            JOIN farmer fr ON fr.farmer_id = fm.farmer_id
            LEFT JOIN soil_type st ON st.soil_type_id = fm.soil_type_id
            LEFT JOIN irrigation_type ir ON ir.irrigation_id = fm.irrigation_id
            $whereSql
            ORDER BY $orderSql, fm.farm_id ASC
            LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $pager['offset'], PDO::PARAM_INT);
    $stmt->execute();
    $farms = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
