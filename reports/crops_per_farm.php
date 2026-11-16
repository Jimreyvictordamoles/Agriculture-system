<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/Report.php';

$report = new Report($pdo);

[$dateFrom, $dateTo, $dateError] = report_dates();
$farmId     = get_int('farm_id');
$cropId     = get_int('crop_id');
$categoryId = get_int('category_id');

$rows       = [];
$farms      = [];
$crops      = [];
$categories = [];
$loadError  = false;

try {
    $farms      = $report->farmOptions();
    $crops      = $report->cropOptions();
    $categories = $report->categoryOptions();

    if (!$dateError) {
        $rows = $report->cropsPerFarm($dateFrom, $dateTo, $farmId, $cropId, $categoryId);
    }
} catch (PDOException $ex) {
    error_log($ex->getMessage());
    $loadError = true;
}

$totalPlantings = 0;
$totalArea      = 0.0;
$farmIds        = [];
foreach ($rows as $row) {
    $totalPlantings += (int) $row['planting_count'];
    $totalArea      += (float) $row['total_area'];
    $farmIds[$row['farm_id']] = true;
}

if (wants_csv() && !$dateError && !$loadError) {
    $csv = [];
    foreach ($rows as $row) {
        $csv[] = [
            $row['farm_name'], $row['first_name'] . ' ' . $row['last_name'], $row['crop_name'],
            $row['variety'] ?? '', $row['category_name'] ?? '', $row['planting_count'], $row['total_area'],
            format_date($row['first_planted']), format_date($row['last_planted']),
        ];
    }
    csv_download('crops_planted_per_farm.csv',
        ['Farm', 'Owner', 'Crop', 'Variety', 'Category', 'Plantings', 'Area planted (ha)', 'First planted', 'Last planted'], $csv);
}

$pageTitle = 'Crops planted per farm';
require __DIR__ . '/../includes/header.php';
echo report_print_styles();
$lastFarm = null;
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-0">Crops planted per farm</h1>
        <div class="text-muted">Planting date: <?= e(report_range_label($dateFrom, $dateTo)) ?></div>
    </div>
    <?= report_toolbar(count($rows) > 0) ?>
</div>

<?php if ($dateError): ?><div class="alert alert-warning"><?= e($dateError) ?></div><?php endif; ?>
<?php if ($loadError): ?><div class="alert alert-danger">Could not load this report. Please try again.</div><?php endif; ?>

<form method="get" action="" class="card card-body mb-3 no-print">
    <div class="row g-2 align-items-end">
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
            <label for="category_id" class="form-label mb-1">Category</label>
            <select class="form-select" id="category_id" name="category_id">
                <option value="">All</option>
                <?php foreach ($categories as $id => $name): ?>
                    <option value="<?= $id ?>" <?= $categoryId === $id ? 'selected' : '' ?>><?= e($name) ?></option>
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
    <div class="mt-2"><a href="crops_per_farm.php" class="small">Reset filters</a></div>
</form>

<div class="row g-3 mb-3">
    <div class="col-4"><div class="card text-center"><div class="card-body py-2"><div class="h4 mb-0 text-success"><?= count($farmIds) ?></div><div class="text-muted small">Farms</div></div></div></div>
    <div class="col-4"><div class="card text-center"><div class="card-body py-2"><div class="h4 mb-0 text-success"><?= $totalPlantings ?></div><div class="text-muted small">Plantings</div></div></div></div>
    <div class="col-4"><div class="card text-center"><div class="card-body py-2"><div class="h4 mb-0 text-success"><?= e(number_format($totalArea, 2)) ?></div><div class="text-muted small">Area planted (ha)</div></div></div></div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
                <tr><th>Farm</th><th>Crop</th><th>Category</th><th class="text-end">Plantings</th><th class="text-end">Area planted (ha)</th><th>First planted</th><th>Last planted</th></tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">No plantings match these filters.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $row): ?>
                <?php $isNewFarm = $lastFarm !== $row['farm_id']; $lastFarm = $row['farm_id']; ?>
                <tr class="<?= $isNewFarm ? 'border-top border-2' : '' ?>">
                    <td>
                        <?php if ($isNewFarm): ?>
                            <strong><?= e($row['farm_name']) ?></strong>
                            <div class="text-muted small"><?= e($row['first_name'] . ' ' . $row['last_name']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= e($row['crop_name']) ?>
                        <?php if ($row['variety'] !== null && $row['variety'] !== ''): ?>
                            <div class="text-muted small"><?= e($row['variety']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?= display_value($row['category_name']) ?></td>
                    <td class="text-end"><?= (int) $row['planting_count'] ?></td>
                    <td class="text-end"><?= e(number_format((float) $row['total_area'], 2)) ?></td>
                    <td class="text-nowrap"><?= e(format_date($row['first_planted'])) ?></td>
                    <td class="text-nowrap"><?= e(format_date($row['last_planted'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
