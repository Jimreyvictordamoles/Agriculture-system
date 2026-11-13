<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/_helpers.php';

[$dateFrom, $dateTo, $dateError] = report_dates();
$farmId   = get_int('farm_id');
$cropId   = get_int('crop_id');
$gradeId  = get_int('grade_id');
$remarkId = get_int('remark_id');

$where  = [];
$params = [];
add_date_range('h.harvest_date', $dateFrom, $dateTo, $where, $params);
if ($farmId > 0) {
    $where[]            = 'p.farm_id = :farm_id';
    $params[':farm_id'] = $farmId;
}
if ($cropId > 0) {
    $where[]            = 'p.crop_id = :crop_id';
    $params[':crop_id'] = $cropId;
}
if ($gradeId > 0) {
    $where[]             = 'h.grade_id = :grade_id';
    $params[':grade_id'] = $gradeId;
}
if ($remarkId > 0) {
    $where[]              = 'h.remark_id = :remark_id';
    $params[':remark_id'] = $remarkId;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$rows      = [];
$farms     = [];
$crops     = [];
$grades    = [];
$remarks   = [];
$loadError = false;

try {
    $farms   = report_farm_options($pdo);
    $crops   = report_crop_options($pdo);
    $grades  = fetch_lookup($pdo, 'quality_grade', 'grade_id', 'grade_name', true);
    $remarks = fetch_lookup($pdo, 'harvest_remark', 'remark_id', 'remark_description', true);

    if (!$dateError) {
        $stmt = $pdo->prepare(
            "SELECT h.harvest_id, h.harvest_date, h.quantity_kg, h.selling_price_per_kg,
                    (h.quantity_kg * h.selling_price_per_kg) AS total_value,
                    c.crop_name, c.variety, fm.farm_name, fr.first_name, fr.last_name,
                    qg.grade_name, hr.remark_description
             FROM harvest h
             JOIN planting p ON p.planting_id = h.planting_id
             JOIN farm fm ON fm.farm_id = p.farm_id
             JOIN farmer fr ON fr.farmer_id = fm.farmer_id
             JOIN crop c ON c.crop_id = p.crop_id
             JOIN harvest_remark hr ON hr.remark_id = h.remark_id
             LEFT JOIN quality_grade qg ON qg.grade_id = h.grade_id
             $whereSql
             ORDER BY h.harvest_date DESC, h.harvest_id DESC"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $ex) {
    error_log($ex->getMessage());
    $loadError = true;
}

$totalKg    = 0.0;
$totalValue = 0.0;
foreach ($rows as $row) {
    $totalKg    += (float) $row['quantity_kg'];
    $totalValue += (float) $row['total_value'];
}

if (wants_csv() && !$dateError && !$loadError) {
    $csv = [];
    foreach ($rows as $row) {
        $csv[] = [
            format_date($row['harvest_date']), $row['crop_name'], $row['variety'] ?? '', $row['farm_name'],
            $row['first_name'] . ' ' . $row['last_name'], $row['quantity_kg'], $row['selling_price_per_kg'] ?? '',
            $row['total_value'] === null ? '' : number_format((float) $row['total_value'], 2, '.', ''),
            $row['grade_name'] ?? '', $row['remark_description'],
        ];
    }
    csv_download('harvest_records.csv',
        ['Harvest date', 'Crop', 'Variety', 'Farm', 'Owner', 'Quantity (kg)', 'Price per kg', 'Estimated value', 'Grade', 'Remark'], $csv);
}

$pageTitle = 'Harvest records';
require __DIR__ . '/../includes/header.php';
echo report_print_styles();
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-0">Harvest records</h1>
        <div class="text-muted">Harvest date: <?= e(report_range_label($dateFrom, $dateTo)) ?></div>
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
            <label class="form-label mb-1" for="date_from">Harvested between</label>
            <div class="input-group">
                <input type="date" class="form-control" id="date_from" name="date_from" value="<?= e($dateFrom) ?>">
                <input type="date" class="form-control" id="date_to" name="date_to" value="<?= e($dateTo) ?>" aria-label="Harvested to">
            </div>
        </div>
    </div>
    <div class="mt-2 d-flex gap-3 align-items-center">
        <button type="submit" class="btn btn-success btn-sm">Apply filters</button>
        <a href="harvest_records.php" class="small">Reset filters</a>
    </div>
</form>

<div class="row g-3 mb-3">
    <div class="col-4"><div class="card text-center"><div class="card-body py-2"><div class="h4 mb-0 text-success"><?= count($rows) ?></div><div class="text-muted small">Harvest records</div></div></div></div>
    <div class="col-4"><div class="card text-center"><div class="card-body py-2"><div class="h4 mb-0 text-success"><?= e(number_format($totalKg, 2)) ?></div><div class="text-muted small">Total kg</div></div></div></div>
    <div class="col-4"><div class="card text-center"><div class="card-body py-2"><div class="h4 mb-0 text-success">&#8369;<?= e(number_format($totalValue, 2)) ?></div><div class="text-muted small">Estimated value</div></div></div></div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
                <tr><th>Date</th><th>Crop</th><th>Farm</th><th class="text-end">Quantity (kg)</th><th class="text-end">Price/kg</th><th class="text-end">Value</th><th>Grade</th><th>Remark</th></tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">No harvests match these filters.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td class="text-nowrap"><?= e(format_date($row['harvest_date'])) ?></td>
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
                    <td class="text-end"><?= e(number_format((float) $row['quantity_kg'], 2)) ?></td>
                    <td class="text-end"><?= $row['selling_price_per_kg'] === null ? display_value(null) : '&#8369;' . e(number_format((float) $row['selling_price_per_kg'], 2)) ?></td>
                    <td class="text-end"><?= $row['total_value'] === null ? display_value(null) : '&#8369;' . e(number_format((float) $row['total_value'], 2)) ?></td>
                    <td><?= display_value($row['grade_name']) ?></td>
                    <td><?= e($row['remark_description']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <?php if ($rows): ?>
            <tfoot class="table-light fw-semibold">
                <tr>
                    <td colspan="3">Total</td>
                    <td class="text-end"><?= e(number_format($totalKg, 2)) ?></td>
                    <td></td>
                    <td class="text-end">&#8369;<?= e(number_format($totalValue, 2)) ?></td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
