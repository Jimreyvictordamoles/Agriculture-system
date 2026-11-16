<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/Report.php';

$report = new Report($pdo);

[$dateFrom, $dateTo, $dateError] = report_dates();
$statusId = get_int('status_id');
$farmId   = get_int('farm_id');
$cropId   = get_int('crop_id');

$summary   = [];
$rows      = [];
$statuses  = [];
$farms     = [];
$crops     = [];
$loadError = false;

try {
    $statuses = $report->statusOptions();
    $farms    = $report->farmOptions();
    $crops    = $report->cropOptions();

    if (!$dateError) {
        $summary = $report->plantingStatusSummary($dateFrom, $dateTo, $farmId, $cropId);
        $rows    = $report->plantingRecords($statusId, $dateFrom, $dateTo, $farmId, $cropId);
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
