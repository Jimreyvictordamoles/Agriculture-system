<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/_helpers.php';

$perPage  = 10;
$q        = trim($_GET['q'] ?? '');
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
    'id'         => ['f.farmer_id'],
    'name'       => ['f.last_name', 'f.first_name'],
    'contact'    => ['f.contact_number'],
    'address'    => ['f.address'],
    'registered' => ['f.date_registered'],
    'farms'      => ['farm_count'],
];
$sort = $_GET['sort'] ?? 'name';
if (!isset($sortMap[$sort])) {
    $sort = 'name';
}
$dir      = (($_GET['dir'] ?? 'asc') === 'desc') ? 'desc' : 'asc';
$orderSql = implode(', ', array_map(fn($column) => "$column $dir NULLS LAST", $sortMap[$sort]));

$where  = [];
$params = [];

if ($q !== '') {
    $where[] = "(f.first_name ILIKE :q1 OR f.last_name ILIKE :q2
                 OR (f.first_name || ' ' || f.last_name) ILIKE :q3
                 OR f.contact_number ILIKE :q4 OR f.address ILIKE :q5)";
    foreach ([':q1', ':q2', ':q3', ':q4', ':q5'] as $name) {
        $params[$name] = '%' . like_escape($q) . '%';
    }
}
if ($dateFrom !== '') {
    $where[]            = 'f.date_registered >= :date_from';
    $params[':date_from'] = $dateFrom;
}
if ($dateTo !== '') {
    $where[]          = 'f.date_registered <= :date_to';
    $params[':date_to'] = $dateTo;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$farmers = [];
$pager   = paginate(0, $perPage, 1);

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM farmer f $whereSql");
    $stmt->execute($params);
    $pager = paginate((int) $stmt->fetchColumn(), $perPage, get_int('page', 1));

    $sql = "SELECT f.farmer_id, f.first_name, f.last_name, f.contact_number, f.address,
                   f.date_registered,
                   (SELECT COUNT(*) FROM farm fm WHERE fm.farmer_id = f.farmer_id) AS farm_count
            FROM farmer f
            $whereSql
            ORDER BY $orderSql, f.farmer_id ASC
            LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $pager['offset'], PDO::PARAM_INT);
    $stmt->execute();
    $farmers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $ex) {
    error_log($ex->getMessage());
    set_flash('danger', 'Could not load the farmers. Please try again.');
}

$pageTitle = 'Farmers';
require __DIR__ . '/../../includes/header.php';

$from = $pager['total'] === 0 ? 0 : $pager['offset'] + 1;
$to   = min($pager['offset'] + $perPage, $pager['total']);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Farmers</h1>
    <a href="add.php" class="btn btn-success">Add farmer</a>
</div>

<form method="get" action="" class="card card-body mb-3">
    <input type="hidden" name="sort" value="<?= e($sort) ?>">
    <input type="hidden" name="dir" value="<?= e($dir) ?>">
    <div class="row g-2 align-items-end">
        <div class="col-md-4">
            <label for="q" class="form-label mb-1">Search</label>
            <input type="text" class="form-control" id="q" name="q" value="<?= e($q) ?>"
                   placeholder="Name, contact number or address">
        </div>
        <div class="col-md-3">
            <label for="date_from" class="form-label mb-1">Registered from</label>
            <input type="date" class="form-control" id="date_from" name="date_from" value="<?= e($dateFrom) ?>">
        </div>
        <div class="col-md-3">
            <label for="date_to" class="form-label mb-1">Registered to</label>
            <input type="date" class="form-control" id="date_to" name="date_to" value="<?= e($dateTo) ?>">
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
                    <th><?= sort_link('Name', 'name', $sort, $dir) ?></th>
                    <th><?= sort_link('Contact', 'contact', $sort, $dir) ?></th>
                    <th><?= sort_link('Address', 'address', $sort, $dir) ?></th>
                    <th><?= sort_link('Registered', 'registered', $sort, $dir) ?></th>
                    <th><?= sort_link('Farms', 'farms', $sort, $dir) ?></th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$farmers): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">No farmers found.</td></tr>
            <?php endif; ?>
            <?php foreach ($farmers as $farmer): ?>
                <tr>
                    <td><?= (int) $farmer['farmer_id'] ?></td>
                    <td>
                        <a href="view.php?id=<?= (int) $farmer['farmer_id'] ?>">
                            <?= e($farmer['first_name'] . ' ' . $farmer['last_name']) ?>
                        </a>
                    </td>
                    <td><?= display_value($farmer['contact_number']) ?></td>
                    <td class="text-break"><?= display_value($farmer['address']) ?></td>
                    <td class="text-nowrap"><?= e(format_date($farmer['date_registered'])) ?></td>
                    <td><?= (int) $farmer['farm_count'] ?></td>
                    <td class="text-end text-nowrap">
                        <a href="view.php?id=<?= (int) $farmer['farmer_id'] ?>" class="btn btn-sm btn-outline-secondary">View</a>
                        <a href="edit.php?id=<?= (int) $farmer['farmer_id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                        <?php if (is_admin()): ?>
                            <form method="post" action="delete.php" class="d-inline"
                                  onsubmit="return confirm('Delete this farmer? This cannot be undone.');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="farmer_id" value="<?= (int) $farmer['farmer_id'] ?>">
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
        Showing <?= $from ?>&ndash;<?= $to ?> of <?= $pager['total'] ?> farmer<?= $pager['total'] === 1 ? '' : 's' ?>
    </div>
    <?= render_pagination($pager) ?>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
