<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$groups = [
    'Farms' => [
        ['👨‍🌾', 'Farmers and their farms', 'farmers_farms.php',
         'Every farmer with each of their farms, soil type and irrigation.',
         'Address, date, soil, irrigation'],
        ['🌾', 'Crops planted per farm', 'crops_per_farm.php',
         'Plantings of each crop per farm, with area planted and first and last planting dates.',
         'Farm, crop'],
    ],
    'Plantings' => [
        ['🌱', 'Planting records by status', 'plantings_by_status.php',
         'Number of plantings in each status, followed by the planting records.',
         'Status, farm, crop, date'],
    ],
    'Harvests' => [
        ['📋', 'Harvest records', 'harvest_records.php',
         'Every harvest with quantity, price, estimated value, grade and remark.',
         'Date, farm, crop, grade, remark'],
        ['⚖️', 'Total harvested quantity', 'harvest_total.php',
         'Total kilograms harvested, grouped by crop, farm or month.',
         'Date range'],
        ['💰', 'Estimated harvest value', 'harvest_value.php',
         'Quantity times selling price, grouped by crop, farm or month.',
         'Date range'],
    ],
];

$pageTitle = 'Reports';
require __DIR__ . '/../includes/header.php';
?>
<style>
    .report-card { border: 0; border-left: 4px solid #198754; transition: transform .15s, box-shadow .15s; }
    .report-card:hover { transform: translateY(-3px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.12) !important; }
    .report-icon { width: 48px; height: 48px; font-size: 1.5rem; background: #e8f5ee; }
    .report-link { color: #198754; font-weight: 600; font-size: .9rem; }
</style>

<div class="mb-4">
    <h1 class="h3 mb-1">Reports</h1>
    <p class="text-muted mb-0">Open a report to filter it, print it or export it to CSV.</p>
</div>

<?php foreach ($groups as $groupName => $items): ?>
    <h2 class="h6 text-uppercase text-muted fw-semibold mt-4 mb-3"><?= e($groupName) ?></h2>
    <div class="row g-3">
        <?php foreach ($items as [$icon, $title, $file, $description, $filters]): ?>
            <div class="col-md-6 col-xl-4">
                <a href="<?= e($file) ?>" class="text-decoration-none text-body">
                    <div class="card report-card h-100 shadow-sm">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex align-items-center gap-3 mb-2">
                                <div class="report-icon rounded-3 d-flex align-items-center justify-content-center"><?= $icon ?></div>
                                <h3 class="h6 mb-0 fw-semibold"><?= e($title) ?></h3>
                            </div>
                            <p class="text-muted small mb-2"><?= e($description) ?></p>
                            <p class="small mb-3"><span class="text-muted">Filters:</span> <?= e($filters) ?></p>
                            <span class="report-link mt-auto">View report &rarr;</span>
                        </div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
<?php endforeach; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>