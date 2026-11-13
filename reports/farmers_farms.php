<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/_helpers.php';

[$dateFrom, $dateTo, $dateError] = report_dates();
$address      = trim($_GET['address'] ?? '');
$soilTypeId   = get_int('soil_type_id');
$irrigationId = get_int('irrigation_id');

$where  = [];
$params = [];
add_date_range('fr.date_registered', $dateFrom, $dateTo, $where, $params);
if ($address !== '') {
    $where[]            = 'fr.address ILIKE :address';
    $params[':address'] = '%' . like_escape($address) . '%';
}
if ($soilTypeId > 0) {
    $where[]                 = 'fm.soil_type_id = :soil_type_id';
    $params[':soil_type_id'] = $soilTypeId;
}
if ($irrigationId > 0) {
    $where[]                  = 'fm.irrigation_id = :irrigation_id';
    $params[':irrigation_id'] = $irrigationId;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$rows            = [];
$soilTypes       = [];
$irrigationTypes = [];
$loadError       = false;

try {
    $soilTypes       = fetch_lookup($pdo, 'soil_type', 'soil_type_id', 'soil_type_name');
    $irrigationTypes = fetch_lookup($pdo, 'irrigation_type', 'irrigation_id', 'irrigation_name');

    if (!$dateError) {
        $stmt = $pdo->prepare(
            "SELECT fr.farmer_id, fr.first_name, fr.last_name, fr.contact_number, fr.address,
                    fr.date_registered, fm.farm_id, fm.farm_name, fm.location, fm.area_hectares,
                    st.soil_type_name, ir.irrigation_name
             FROM farmer fr
             LEFT JOIN farm fm ON fm.farmer_id = fr.farmer_id
             LEFT JOIN soil_type st ON st.soil_type_id = fm.soil_type_id
             LEFT JOIN irrigation_type ir ON ir.irrigation_id = fm.irrigation_id
             $whereSql
             ORDER BY fr.last_name, fr.first_name, fr.farmer_id, fm.farm_name"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $ex) {
    error_log($ex->getMessage());
    $loadError = true;
}

// Totals
$farmerIds = [];
$farmCount = 0;
$totalArea = 0.0;
foreach ($rows as $row) {
    $farmerIds[$row['farmer_id']] = true;
    if ($row['farm_id'] !== null) {
        $farmCount++;
        $totalArea += (float) $row['area_hectares'];
    }
}

if (wants_csv() && !$dateError && !$loadError) {
    $csv = [];
    foreach ($rows as $row) {
        $csv[] = [
            $row['first_name'] . ' ' . $row['last_name'], $row['contact_number'], $row['address'],
            format_date($row['date_registered']), $row['farm_name'] ?? '', $row['location'] ?? '',
            $row['area_hectares'] ?? '', $row['soil_type_name'] ?? '', $row['irrigation_name'] ?? '',
        ];
    }
    csv_download('farmers_and_farms.csv',
        ['Farmer', 'Contact', 'Address', 'Registered', 'Farm', 'Farm location', 'Area (ha)', 'Soil type', 'Irrigation'], $csv);
}

$pageTitle = 'Farmers and their farms';
require __DIR__ . '/../includes/header.php';
echo report_print_styles();
$lastFarmer = null;
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-0">Farmers and their farms</h1>
        <div class="text-muted">Registered: <?= e(report_range_label($dateFrom, $dateTo)) ?></div>
    </div>
    <?= report_toolbar(count($rows) > 0) ?>
</div>

<?php if ($dateError): ?><div class="alert alert-warning"><?= e($dateError) ?></div><?php endif; ?>
<?php if ($loadError): ?><div class="alert alert-danger">Could not load this report. Please try again.</div><?php endif; ?>

<form method="get" action="" class="card card-body mb-3 no-print">
    <div class="row g-2 align-items-end">
        <div class="col-md-3">
            <label for="address" class="form-label mb-1">Farmer address contains</label>
            <input type="text" class="form-control" id="address" name="address" value="<?= e($address) ?>" placeholder="e.g. Cebu City">
        </div>
        <div class="col-md-2">
            <label for="soil_type_id" class="form-label mb-1">Soil type</label>
            <select class="form-select" id="soil_type_id" name="soil_type_id">
                <option value="">All</option>
                <?php foreach ($soilTypes as $id => $name): ?>
                    <option value="<?= $id ?>" <?= $soilTypeId === $id ? 'selected' : '' ?>><?= e($name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label for="irrigation_id" class="form-label mb-1">Irrigation</label>
            <select class="form-select" id="irrigation_id" name="irrigation_id">
                <option value="">All</option>
                <?php foreach ($irrigationTypes as $id => $name): ?>
                    <option value="<?= $id ?>" <?= $irrigationId === $id ? 'selected' : '' ?>><?= e($name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label for="date_from" class="form-label mb-1">Registered from</label>
            <input type="date" class="form-control" id="date_from" name="date_from" value="<?= e($dateFrom) ?>">
        </div>
        <div class="col-md-2">
            <label for="date_to" class="form-label mb-1">Registered to</label>
            <input type="date" class="form-control" id="date_to" name="date_to" value="<?= e($dateTo) ?>">
        </div>
        <div class="col-md-1 d-grid">
            <button type="submit" class="btn btn-success">Go</button>
        </div>
    </div>
    <div class="mt-2"><a href="farmers_farms.php" class="small">Reset filters</a></div>
</form>

<div class="row g-3 mb-3">
    <div class="col-4"><div class="card text-center"><div class="card-body py-2"><div class="h4 mb-0 text-success"><?= count($farmerIds) ?></div><div class="text-muted small">Farmers</div></div></div></div>
    <div class="col-4"><div class="card text-center"><div class="card-body py-2"><div class="h4 mb-0 text-success"><?= $farmCount ?></div><div class="text-muted small">Farms</div></div></div></div>
    <div class="col-4"><div class="card text-center"><div class="card-body py-2"><div class="h4 mb-0 text-success"><?= e(number_format($totalArea, 2)) ?></div><div class="text-muted small">Total area (ha)</div></div></div></div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
                <tr><th>Farmer</th><th>Contact</th><th>Farm</th><th>Farm location</th><th class="text-end">Area (ha)</th><th>Soil</th><th>Irrigation</th></tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">No records match these filters.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $row): ?>
                <?php $isNewFarmer = $lastFarmer !== $row['farmer_id']; $lastFarmer = $row['farmer_id']; ?>
                <tr class="<?= $isNewFarmer ? 'border-top border-2' : '' ?>">
                    <td>
                        <?php if ($isNewFarmer): ?>
                            <strong><?= e($row['first_name'] . ' ' . $row['last_name']) ?></strong>
                            <div class="text-muted small"><?= e($row['address']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="text-nowrap"><?= $isNewFarmer ? e($row['contact_number']) : '' ?></td>
                    <?php if ($row['farm_id'] === null): ?>
                        <td colspan="5" class="text-muted">No farms registered</td>
                    <?php else: ?>
                        <td><?= e($row['farm_name']) ?></td>
                        <td><?= e($row['location']) ?></td>
                        <td class="text-end"><?= $row['area_hectares'] === null ? '' : e(number_format((float) $row['area_hectares'], 2)) ?></td>
                        <td><?= display_value($row['soil_type_name']) ?></td>
                        <td><?= display_value($row['irrigation_name']) ?></td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
