<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/_helpers.php';

$reportKey = 'harvest_total';   // decides which columns are shown

[$dateFrom, $dateTo, $dateError] = report_dates();
$farmId  = get_int('farm_id');
$cropId  = get_int('crop_id');
$groupBy = $_GET['group_by'] ?? 'crop';
if (!isset(harvest_group_options()[$groupBy])) {
    $groupBy = 'crop';
}

$rows      = [];
$farms     = [];
$crops     = [];
$loadError = false;

try {
    $farms = report_farm_options($pdo);
    $crops = report_crop_options($pdo);
    if (!$dateError) {
        $rows = harvest_summary($pdo, $groupBy, $dateFrom, $dateTo, $farmId, $cropId);
    }
} catch (PDOException $ex) {
    error_log($ex->getMessage());
    $loadError = true;
}

$totalKg      = 0.0;
$totalValue   = 0.0;
$totalHarvest = 0;
foreach ($rows as $row) {
    $totalKg      += (float) $row['total_kg'];
    $totalValue   += (float) $row['total_value'];
    $totalHarvest += (int) $row['harvest_count'];
}
$groupLabel = harvest_group_options()[$groupBy];
$isQuantity = $reportKey === 'harvest_total';

if (wants_csv() && !$dateError && !$loadError) {
    $csv = [];
    foreach ($rows as $row) {
        $kg    = (float) $row['total_kg'];
        $value = (float) $row['total_value'];
        if ($isQuantity) {
            $csv[] = [$row['label'], $row['harvest_count'], number_format($kg, 2, '.', ''),
                      $totalKg > 0 ? number_format($kg / $totalKg * 100, 1, '.', '') : ''];
        } else {
            $csv[] = [$row['label'], number_format($kg, 2, '.', ''),
                      $kg > 0 ? number_format($value / $kg, 2, '.', '') : '',
                      number_format($value, 2, '.', ''),
                      $totalValue > 0 ? number_format($value / $totalValue * 100, 1, '.', '') : ''];
        }
    }
    csv_download('total_harvested_quantity.csv',
        $isQuantity
            ? [$groupLabel, 'Harvests', 'Total quantity (kg)', 'Share of total (%)']
            : [$groupLabel, 'Total quantity (kg)', 'Average price per kg', 'Estimated value', 'Share of total (%)'],
        $csv);
}

$pageTitle = 'Total harvested quantity';
require __DIR__ . '/../includes/header.php';
echo report_print_styles();
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-0">Total harvested quantity</h1>
        <div class="text-muted">Harvest date: <?= e(report_range_label($dateFrom, $dateTo)) ?> &middot; Grouped by <?= e(strtolower($groupLabel)) ?></div>
    </div>
    <?= report_toolbar(count($rows) > 0) ?>
</div>

<?php if ($dateError): ?><div class="alert alert-warning"><?= e($dateError) ?></div><?php endif; ?>
<?php if ($loadError): ?><div class="alert alert-danger">Could not load this report. Please try again.</div><?php endif; ?>

<form method="get" action="" class="card card-body mb-3 no-print">
    <div class="row g-2 align-items-end">
        <div class="col-md-2">
            <label for="group_by" class="form-label mb-1">Group by</label>
            <select class="form-select" id="group_by" name="group_by">
                <?php foreach (harvest_group_options() as $key => $name): ?>
                    <option value="<?= e($key) ?>" <?= $groupBy === $key ? 'selected' : '' ?>><?= e($name) ?></option>
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
            <label for="date_from" class="form-label mb-1">Harvested from</label>
            <input type="date" class="form-control" id="date_from" name="date_from" value="<?= e($dateFrom) ?>">
        </div>
        <div class="col-md-2">
            <label for="date_to" class="form-label mb-1">Harvested to</label>
            <input type="date" class="form-control" id="date_to" name="date_to" value="<?= e($dateTo) ?>">
        </div>
        <div class="col-md-1 d-grid">
            <button type="submit" class="btn btn-success">Go</button>
        </div>
    </div>
    <div class="mt-2"><a href="harvest_total.php" class="small">Reset filters</a></div>
</form>

<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card text-center border-success"><div class="card-body py-2"><div class="h3 mb-0 text-success"><?= $isQuantity ? e(number_format($totalKg, 2)) . ' kg' : '&#8369;' . e(number_format($totalValue, 2)) ?></div><div class="text-muted small"><?= $isQuantity ? 'Total harvested quantity' : 'Estimated harvest value' ?></div></div></div></div>
    <div class="col-md-4"><div class="card text-center"><div class="card-body py-2"><div class="h4 mb-0 text-success"><?= $isQuantity ? '&#8369;' . e(number_format($totalValue, 2)) : e(number_format($totalKg, 2)) . ' kg' ?></div><div class="text-muted small"><?= $isQuantity ? 'Estimated value' : 'Total quantity' ?></div></div></div></div>
    <div class="col-md-4"><div class="card text-center"><div class="card-body py-2"><div class="h4 mb-0 text-success"><?= $totalHarvest ?></div><div class="text-muted small">Harvest records</div></div></div></div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
                <?php if ($isQuantity): ?>
                    <tr><th><?= e($groupLabel) ?></th><th class="text-end">Harvests</th><th class="text-end">Total quantity (kg)</th><th class="text-end">Share of total</th></tr>
                <?php else: ?>
                    <tr><th><?= e($groupLabel) ?></th><th class="text-end">Quantity (kg)</th><th class="text-end">Average price/kg</th><th class="text-end">Estimated value</th><th class="text-end">Share of total</th></tr>
                <?php endif; ?>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">No harvests match these filters.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $row): ?>
                <?php $kg = (float) $row['total_kg']; $value = (float) $row['total_value']; ?>
                <tr>
                    <td><?= e($row['label']) ?></td>
                    <?php if ($isQuantity): ?>
                        <td class="text-end"><?= (int) $row['harvest_count'] ?></td>
                        <td class="text-end"><?= e(number_format($kg, 2)) ?></td>
                        <td class="text-end"><?= $totalKg > 0 ? e(number_format($kg / $totalKg * 100, 1)) . '%' : '-' ?></td>
                    <?php else: ?>
                        <td class="text-end"><?= e(number_format($kg, 2)) ?></td>
                        <td class="text-end"><?= $kg > 0 ? '&#8369;' . e(number_format($value / $kg, 2)) : '-' ?></td>
                        <td class="text-end">&#8369;<?= e(number_format($value, 2)) ?></td>
                        <td class="text-end"><?= $totalValue > 0 ? e(number_format($value / $totalValue * 100, 1)) . '%' : '-' ?></td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <?php if ($rows): ?>
            <tfoot class="table-light fw-semibold">
                <?php if ($isQuantity): ?>
                    <tr><td>Total</td><td class="text-end"><?= $totalHarvest ?></td><td class="text-end"><?= e(number_format($totalKg, 2)) ?></td><td class="text-end">100%</td></tr>
                <?php else: ?>
                    <tr><td>Total</td><td class="text-end"><?= e(number_format($totalKg, 2)) ?></td><td class="text-end"><?= $totalKg > 0 ? '&#8369;' . e(number_format($totalValue / $totalKg, 2)) : '-' ?></td><td class="text-end">&#8369;<?= e(number_format($totalValue, 2)) ?></td><td class="text-end">100%</td></tr>
                <?php endif; ?>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
