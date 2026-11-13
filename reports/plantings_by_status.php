<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/_helpers.php';

[$dateFrom, $dateTo, $dateError] = report_dates();
$statusId = get_int('status_id');
$farmId   = get_int('farm_id');
$cropId   = get_int('crop_id');

// Filters that apply to every planting
$baseWhere  = [];
$baseParams = [];
add_date_range('p.planting_date', $dateFrom, $dateTo, $baseWhere, $baseParams);
if ($farmId > 0) {
    $baseWhere[]            = 'p.farm_id = :farm_id';
    $baseParams[':farm_id'] = $farmId;
}
if ($cropId > 0) {
    $baseWhere[]            = 'p.crop_id = :crop_id';
    $baseParams[':crop_id'] = $cropId;
}

$summary   = [];
$rows      = [];
$statuses  = [];
$farms     = [];
$crops     = [];
$loadError = false;

try {
    $statuses = fetch_lookup($pdo, 'planting_status', 'status_id', 'status_name', true);
    $farms    = report_farm_options($pdo);
    $crops    = report_crop_options($pdo);

    if (!$dateError) {
        // Summary: statuses with no plantings still appear (the filters go in the JOIN)
        $joinSql = $baseWhere ? 'AND ' . implode(' AND ', $baseWhere) : '';
        $stmt = $pdo->prepare(
            "SELECT ps.status_id, ps.status_name, COUNT(p.planting_id) AS planting_count,
                    COALESCE(SUM(p.area_planted), 0) AS total_area
             FROM planting_status ps
             LEFT JOIN planting p ON p.status_id = ps.status_id $joinSql
             GROUP BY ps.status_id, ps.status_name
             ORDER BY ps.status_id"
        );
        $stmt->execute($baseParams);
        $summary = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Detail list
        $where  = $baseWhere;
        $params = $baseParams;
        if ($statusId > 0) {
            $where[]              = 'p.status_id = :status_id';
            $params[':status_id'] = $statusId;
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmt = $pdo->prepare(
            "SELECT p.planting_id, p.planting_date, p.expected_harvest_date, p.area_planted,
                    c.crop_name, c.variety, fm.farm_name, fr.first_name, fr.last_name, ps.status_name
             FROM planting p
             JOIN farm fm ON fm.farm_id = p.farm_id
             JOIN farmer fr ON fr.farmer_id = fm.farmer_id
             JOIN crop c ON c.crop_id = p.crop_id
             JOIN planting_status ps ON ps.status_id = p.status_id
             $whereSql
             ORDER BY ps.status_id, p.planting_date DESC, p.planting_id DESC"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $ex) {
    error_log($ex->getMessage());
    $loadError = true;
}

$grandCount = 0;
$grandArea  = 0.0;
foreach ($summary as $row) {
    $grandCount += (int) $row['planting_count'];
    $grandArea  += (float) $row['total_area'];
}

if (wants_csv() && !$dateError && !$loadError) {
    $csv = [];
    foreach ($rows as $row) {
        $csv[] = [
            $row['status_name'], $row['crop_name'], $row['variety'] ?? '', $row['farm_name'],
            $row['first_name'] . ' ' . $row['last_name'], format_date($row['planting_date']),
            format_date($row['expected_harvest_date']), $row['area_planted'] ?? '',
        ];
    }
    csv_download('plantings_by_status.csv',
        ['Status', 'Crop', 'Variety', 'Farm', 'Owner', 'Planted', 'Expected harvest', 'Area planted (ha)'], $csv);
}

$pageTitle = 'Planting records by status';
require __DIR__ . '/../includes/header.php';
echo report_print_styles();
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-0">Planting records by status</h1>
        <div class="text-muted">Planting date: <?= e(report_range_label($dateFrom, $dateTo)) ?></div>
    </div>
    <?= report_toolbar(count($rows) > 0) ?>
</div>

<?php if ($dateError): ?><div class="alert alert-warning"><?= e($dateError) ?></div><?php endif; ?>
<?php if ($loadError): ?><div class="alert alert-danger">Could not load this report. Please try again.</div><?php endif; ?>

<form method="get" action="" class="card card-body mb-3 no-print">
    <div class="row g-2 align-items-end">
        <div class="col-md-2">
            <label for="status_id" class="form-label mb-1">Status</label>
            <select class="form-select" id="status_id" name="status_id">
                <option value="">All statuses</option>
                <?php foreach ($statuses as $id => $name): ?>
                    <option value="<?= $id ?>" <?= $statusId === $id ? 'selected' : '' ?>><?= e($name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label for="farm_id" class="form-label mb-1">Farm</label>
            <select class="form-select" id="farm_id" name="farm_id">
                <option value="">All farms</option>
                <?php foreach ($farms as $id => $label): ?>
                    <option value="<?= $id ?>" <?= $farmId === $id ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label for="crop_id" class="form-label mb-1">Crop</label>
            <select class="form-select" id="crop_id" name="crop_id">
                <option value="">All crops</option>
                <?php foreach ($crops as $id => $label): ?>
                    <option value="<?= $id ?>" <?= $cropId === $id ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label for="date_from" class="form-label mb-1">Planted from</label>
            <input type="date" class="form-control" id="date_from" name="date_from" value="<?= e($dateFrom) ?>">
        </div>
        <div class="col-md-2">
            <label for="date_to" class="form-label mb-1">Planted to</label>
            <input type="date" class="form-control" id="date_to" name="date_to" value="<?= e($dateTo) ?>">
        </div>
        <div class="col-md-1 d-grid">
            <button type="submit" class="btn btn-success">Go</button>
        </div>
    </div>
    <div class="mt-2"><a href="plantings_by_status.php" class="small">Reset filters</a></div>
</form>

<div class="card mb-3">
    <div class="card-header bg-white fw-semibold">Summary</div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light"><tr><th>Status</th><th class="text-end">Plantings</th><th class="text-end">Area planted (ha)</th><th class="text-end">Share</th></tr></thead>
            <tbody>
            <?php foreach ($summary as $row): ?>
                <tr>
                    <td><?= status_badge($row['status_name']) ?></td>
                    <td class="text-end"><?= (int) $row['planting_count'] ?></td>
                    <td class="text-end"><?= e(number_format((float) $row['total_area'], 2)) ?></td>
                    <td class="text-end"><?= $grandCount > 0 ? e(number_format((int) $row['planting_count'] / $grandCount * 100, 1)) . '%' : '-' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot class="table-light fw-semibold">
                <tr><td>Total</td><td class="text-end"><?= $grandCount ?></td><td class="text-end"><?= e(number_format($grandArea, 2)) ?></td><td></td></tr>
            </tfoot>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header bg-white fw-semibold">Planting records (<?= count($rows) ?>)</div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
                <tr><th>Status</th><th>Crop</th><th>Farm</th><th>Planted</th><th>Expected harvest</th><th class="text-end">Area (ha)</th></tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No plantings match these filters.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= status_badge($row['status_name']) ?></td>
                    <td>
                        <?= e($row['crop_name']) ?>
                        <?php if ($row['variety'] !== null && $row['variety'] !== ''): ?>
                            <div class="text-muted small"><?= e($row['variety']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= e($row['farm_name']) ?>
                        <div class="text-muted small"><?= e($row['first_name'] . ' ' . $row['last_name']) ?></div>
                    </td>
                    <td class="text-nowrap"><?= e(format_date($row['planting_date'])) ?></td>
                    <td class="text-nowrap"><?= $row['expected_harvest_date'] === null ? display_value(null) : e(format_date($row['expected_harvest_date'])) ?></td>
                    <td class="text-end"><?= $row['area_planted'] === null ? display_value(null) : e(number_format((float) $row['area_planted'], 2)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
