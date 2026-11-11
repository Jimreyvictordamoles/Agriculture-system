<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$perPage    = 10;
$q          = trim($_GET['q'] ?? '');
$categoryId = get_int('category_id');
$seasonId   = get_int('season_id');

// Only these columns may be used for sorting (never trust ?sort= directly)
$sortMap = [
    'id'       => 'c.crop_id',
    'name'     => 'c.crop_name',
    'variety'  => 'c.variety',
    'category' => 'cc.category_name',
    'season'   => 's.season_name',
    'days'     => 'c.growing_period_days',
];
$sort = $_GET['sort'] ?? 'name';
if (!isset($sortMap[$sort])) {
    $sort = 'name';
}
$dir = (($_GET['dir'] ?? 'asc') === 'desc') ? 'desc' : 'asc';

$where  = [];
$params = [];

if ($q !== '') {
    $where[]       = '(c.crop_name ILIKE :q1 OR c.variety ILIKE :q2)';
    $params[':q1'] = '%' . like_escape($q) . '%';
    $params[':q2'] = '%' . like_escape($q) . '%';
}
if ($categoryId > 0) {
    $where[]                = 'c.category_id = :category_id';
    $params[':category_id'] = $categoryId;
}
if ($seasonId > 0) {
    $where[]              = 'c.season_id = :season_id';
    $params[':season_id'] = $seasonId;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$crops      = [];
$categories = [];
$seasons    = [];
$pager      = paginate(0, $perPage, 1);

try {
    $categories = fetch_lookup($pdo, 'crop_category', 'category_id', 'category_name');
    $seasons    = fetch_lookup($pdo, 'season', 'season_id', 'season_name');

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM crop c $whereSql");
    $stmt->execute($params);
    $pager = paginate((int) $stmt->fetchColumn(), $perPage, get_int('page', 1));

    $sql = "SELECT c.crop_id, c.crop_name, c.variety, c.growing_period_days,
                   cc.category_name, s.season_name
            FROM crop c
            LEFT JOIN crop_category cc ON cc.category_id = c.category_id
            LEFT JOIN season s ON s.season_id = c.season_id
            $whereSql
            ORDER BY {$sortMap[$sort]} $dir NULLS LAST, c.crop_id ASC
            LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $pager['offset'], PDO::PARAM_INT);
    $stmt->execute();
    $crops = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $ex) {
    error_log($ex->getMessage());
    set_flash('danger', 'Could not load the crops. Please try again.');
}

$pageTitle = 'Crops';
require __DIR__ . '/../../includes/header.php';

$from = $pager['total'] === 0 ? 0 : $pager['offset'] + 1;
$to   = min($pager['offset'] + $perPage, $pager['total']);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Crops</h1>
    <a href="add.php" class="btn btn-success">Add crop</a>
</div>

<form method="get" action="" class="card card-body mb-3">
    <input type="hidden" name="sort" value="<?= e($sort) ?>">
    <input type="hidden" name="dir" value="<?= e($dir) ?>">
    <div class="row g-2 align-items-end">
        <div class="col-md-4">
            <label for="q" class="form-label mb-1">Search</label>
            <input type="text" class="form-control" id="q" name="q" value="<?= e($q) ?>"
                   placeholder="Crop name or variety">
        </div>
        <div class="col-md-3">
            <label for="category_id" class="form-label mb-1">Category</label>
            <select class="form-select" id="category_id" name="category_id">
                <option value="">All categories</option>
                <?php foreach ($categories as $id => $name): ?>
                    <option value="<?= $id ?>" <?= $categoryId === $id ? 'selected' : '' ?>><?= e($name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label for="season_id" class="form-label mb-1">Season</label>
            <select class="form-select" id="season_id" name="season_id">
                <option value="">All seasons</option>
                <?php foreach ($seasons as $id => $name): ?>
                    <option value="<?= $id ?>" <?= $seasonId === $id ? 'selected' : '' ?>><?= e($name) ?></option>
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
                    <th><?= sort_link('Crop', 'name', $sort, $dir) ?></th>
                    <th><?= sort_link('Variety', 'variety', $sort, $dir) ?></th>
                    <th><?= sort_link('Category', 'category', $sort, $dir) ?></th>
                    <th><?= sort_link('Season', 'season', $sort, $dir) ?></th>
                    <th><?= sort_link('Growing days', 'days', $sort, $dir) ?></th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$crops): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">No crops found.</td></tr>
            <?php endif; ?>
            <?php foreach ($crops as $crop): ?>
                <tr>
                    <td><?= (int) $crop['crop_id'] ?></td>
                    <td><a href="view.php?id=<?= (int) $crop['crop_id'] ?>"><?= e($crop['crop_name']) ?></a></td>
                    <td><?= display_value($crop['variety']) ?></td>
                    <td><?= display_value($crop['category_name']) ?></td>
                    <td><?= display_value($crop['season_name']) ?></td>
                    <td><?= display_value($crop['growing_period_days']) ?></td>
                    <td class="text-end text-nowrap">
                        <a href="view.php?id=<?= (int) $crop['crop_id'] ?>" class="btn btn-sm btn-outline-secondary">View</a>
                        <a href="edit.php?id=<?= (int) $crop['crop_id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                        <?php if (is_admin()): ?>
                            <form method="post" action="delete.php" class="d-inline"
                                  onsubmit="return confirm('Delete this crop? This cannot be undone.');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="crop_id" value="<?= (int) $crop['crop_id'] ?>">
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
        Showing <?= $from ?>&ndash;<?= $to ?> of <?= $pager['total'] ?> crop<?= $pager['total'] === 1 ? '' : 's' ?>
    </div>
    <?= render_pagination($pager) ?>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
