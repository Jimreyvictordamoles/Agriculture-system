<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
// Brings in format_date() and status_badge() (shared helper functions)
require_once __DIR__ . '/modules/plantings/_helpers.php';

$counts = ['farmers' => 0, 'farms' => 0, 'crops' => 0, 'plantings' => 0, 'harvests' => 0];
$totalKg          = 0.0;
$totalValue       = 0.0;
$statusCounts     = [];
$recentPlantings  = [];
$recentHarvests   = [];

try {
    // Summary card numbers
    $counts = $pdo->query(
        'SELECT (SELECT COUNT(*) FROM farmer)   AS farmers,
                (SELECT COUNT(*) FROM farm)     AS farms,
                (SELECT COUNT(*) FROM crop)     AS crops,
                (SELECT COUNT(*) FROM planting) AS plantings,
                (SELECT COUNT(*) FROM harvest)  AS harvests'
    )->fetch(PDO::FETCH_ASSOC);

    // Total harvested quantity and estimated value
    [$totalKg, $totalValue] = $pdo->query(
        'SELECT COALESCE(SUM(quantity_kg), 0),
                COALESCE(SUM(quantity_kg * selling_price_per_kg), 0)
         FROM harvest'
    )->fetch(PDO::FETCH_NUM);

    // Plantings by status (statuses with no plantings still show up as 0)
    $statusCounts = $pdo->query(
        'SELECT ps.status_name, COUNT(p.planting_id) AS total
         FROM planting_status ps
         LEFT JOIN planting p ON p.status_id = ps.status_id
         GROUP BY ps.status_id, ps.status_name
         ORDER BY ps.status_id'
    )->fetchAll(PDO::FETCH_ASSOC);

    // The 5 most recently added plantings
    $recentPlantings = $pdo->query(
        'SELECT p.planting_id, p.planting_date, c.crop_name, c.variety, fm.farm_name, ps.status_name
         FROM planting p
         JOIN crop c ON c.crop_id = p.crop_id
         JOIN farm fm ON fm.farm_id = p.farm_id
         JOIN planting_status ps ON ps.status_id = p.status_id
         ORDER BY p.created_at DESC, p.planting_id DESC
         LIMIT 5'
    )->fetchAll(PDO::FETCH_ASSOC);

    // The 5 most recently added harvests
    $recentHarvests = $pdo->query(
        'SELECT h.harvest_id, h.harvest_date, h.quantity_kg,
                (h.quantity_kg * h.selling_price_per_kg) AS total_value,
                c.crop_name, c.variety, fm.farm_name
         FROM harvest h
         JOIN planting p ON p.planting_id = h.planting_id
         JOIN crop c ON c.crop_id = p.crop_id
         JOIN farm fm ON fm.farm_id = p.farm_id
         ORDER BY h.created_at DESC, h.harvest_id DESC
         LIMIT 5'
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $ex) {
    error_log($ex->getMessage());
    set_flash('danger', 'Could not load all of the dashboard data. Please refresh the page.');
}

// Data for the chart
$chartLabels = array_column($statusCounts, 'status_name');
$chartData   = array_map('intval', array_column($statusCounts, 'total'));
$chartColors = [
    'Planted'           => '#6c757d',
    'Growing'           => '#0d6efd',
    'Ready for Harvest' => '#ffc107',
    'Harvested'         => '#198754',
    'Failed'            => '#dc3545',
];
$chartBackground = array_map(fn($label) => $chartColors[$label] ?? '#adb5bd', $chartLabels);
$hasPlantings    = array_sum($chartData) > 0;

$cards = [
    ['Farmers',   $counts['farmers'],   'modules/farmers/index.php'],
    ['Farms',     $counts['farms'],     'modules/farms/index.php'],
    ['Crops',     $counts['crops'],     'modules/crops/index.php'],
    ['Plantings', $counts['plantings'], 'modules/plantings/index.php'],
    ['Harvests',  $counts['harvests'],  'modules/harvests/index.php'],
];

$pageTitle = 'Dashboard';
require __DIR__ . '/includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h3 mb-1">Dashboard</h1>
        <p class="text-muted mb-0">
            Welcome, <strong><?= e($user['full_name']) ?></strong>
            <span class="badge bg-success"><?= e($user['role_name']) ?></span>
        </p>
    </div>
</div>

<!-- Summary cards -->
<div class="row g-3 mb-3">
    <?php foreach ($cards as [$label, $number, $link]): ?>
        <div class="col-6 col-md">
            <a href="<?= BASE_URL . e($link) ?>" class="text-decoration-none">
                <div class="card text-center h-100 shadow-sm">
                    <div class="card-body">
                        <div class="display-6 fw-semibold text-success"><?= number_format((int) $number) ?></div>
                        <div class="text-muted"><?= e($label) ?></div>
                    </div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<!-- Harvest totals -->
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card h-100 border-success">
            <div class="card-body">
                <div class="text-muted">Total harvested quantity</div>
                <div class="h2 mb-0 text-success"><?= e(number_format((float) $totalKg, 2)) ?> kg</div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100 border-success">
            <div class="card-body">
                <div class="text-muted">Estimated harvest value</div>
                <div class="h2 mb-0 text-success">&#8369;<?= e(number_format((float) $totalValue, 2)) ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Plantings by status -->
<div class="card mb-4">
    <div class="card-header bg-white fw-semibold">Plantings by status</div>
    <div class="card-body">
        <div class="row align-items-center g-3">
            <div class="col-md-6">
                <?php if ($hasPlantings): ?>
                    <div style="position: relative; height: 260px;">
                        <canvas id="statusChart" aria-label="Plantings by status chart" role="img"></canvas>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center my-4">No plantings yet, so there is nothing to chart.</p>
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light"><tr><th>Status</th><th class="text-end">Plantings</th></tr></thead>
                    <tbody>
                    <?php foreach ($statusCounts as $row): ?>
                        <tr>
                            <td><?= status_badge($row['status_name']) ?></td>
                            <td class="text-end"><?= (int) $row['total'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Recent records -->
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Recent plantings</span>
                <a href="<?= BASE_URL ?>modules/plantings/index.php" class="small">View all</a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light"><tr><th>Crop</th><th>Farm</th><th>Planted</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php if (!$recentPlantings): ?>
                        <tr><td colspan="4" class="text-center text-muted py-3">No plantings yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($recentPlantings as $planting): ?>
                        <tr>
                            <td>
                                <a href="<?= BASE_URL ?>modules/plantings/view.php?id=<?= (int) $planting['planting_id'] ?>">
                                    <?= e($planting['crop_name']) ?>
                                </a>
                                <?php if ($planting['variety'] !== null && $planting['variety'] !== ''): ?>
                                    <div class="text-muted small"><?= e($planting['variety']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= e($planting['farm_name']) ?></td>
                            <td class="text-nowrap"><?= e(format_date($planting['planting_date'])) ?></td>
                            <td><?= status_badge($planting['status_name']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Recent harvests</span>
                <a href="<?= BASE_URL ?>modules/harvests/index.php" class="small">View all</a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light"><tr><th>Date</th><th>Crop</th><th>Farm</th><th class="text-end">Quantity</th><th class="text-end">Value</th></tr></thead>
                    <tbody>
                    <?php if (!$recentHarvests): ?>
                        <tr><td colspan="5" class="text-center text-muted py-3">No harvests yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($recentHarvests as $harvest): ?>
                        <tr>
                            <td class="text-nowrap">
                                <a href="<?= BASE_URL ?>modules/harvests/view.php?id=<?= (int) $harvest['harvest_id'] ?>">
                                    <?= e(format_date($harvest['harvest_date'])) ?>
                                </a>
                            </td>
                            <td>
                                <?= e($harvest['crop_name']) ?>
                                <?php if ($harvest['variety'] !== null && $harvest['variety'] !== ''): ?>
                                    <div class="text-muted small"><?= e($harvest['variety']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= e($harvest['farm_name']) ?></td>
                            <td class="text-end text-nowrap"><?= e(number_format((float) $harvest['quantity_kg'], 2)) ?> kg</td>
                            <td class="text-end text-nowrap">
                                <?= $harvest['total_value'] === null ? display_value(null) : '&#8369;' . e(number_format((float) $harvest['total_value'], 2)) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if ($hasPlantings): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
// If the chart library cannot load (for example, no internet), the table next to it still shows the numbers.
if (typeof Chart !== 'undefined') {
    new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($chartLabels, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            datasets: [{
                data: <?= json_encode($chartData) ?>,
                backgroundColor: <?= json_encode($chartBackground) ?>,
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } }
        }
    });
}
</script>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>