<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/HarvestValidator.php';
require_login();

$harvestModel = new Harvest($pdo);
$validator    = new HarvestValidator($harvestModel);

$errors        = [];
$plantings     = [];
$grades        = [];
$remarks       = [];
$markHarvested = false;
$values        = [
    'planting_id'          => '',
    'harvest_date'         => today_manila(),
    'quantity_kg'          => '',
    'selling_price_per_kg' => '',
    'grade_id'             => '',
    'remark_id'            => '',
];

try {
    $plantings = $harvestModel->fetchPlantingOptions();
    $grades    = $harvestModel->fetchGrades();
    $remarks   = $harvestModel->fetchRemarks();

    // Allows add.php?planting_id=5 to pre-select the planting (used by "Record harvest")
    $preselect = filter_var($_GET['planting_id'] ?? null, FILTER_VALIDATE_INT);
    if ($preselect && isset($plantings[$preselect])) {
        $values['planting_id'] = (string) $preselect;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $markHarvested = !empty($_POST['mark_harvested']);

        if (!verify_csrf()) {
            $errors[] = 'Your session expired. Please try again.';
        } else {
            $result = $validator->validate($_POST, $plantings, $grades, $remarks);
            $errors = $result['errors'];
            $values = $result['values'];

            if (!$errors) {
                $harvestModel->createAndMarkPlanting($result['clean'], $markHarvested);
                set_flash('success', 'Harvest recorded successfully.');
                redirect('modules/harvests/index.php');
            }
        }
    }
} catch (PDOException $ex) {
    error_log($ex->getMessage());
    $errors[] = 'Something went wrong. Please try again.';
}

$pageTitle         = 'Add harvest';
$submitLabel       = 'Save harvest';
$showMarkHarvested = true;
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h3 mb-3">Add harvest</h1>
<?php if (!$plantings && !$errors): ?>
    <div class="alert alert-warning">
        A harvest belongs to a planting. <a href="<?= BASE_URL ?>modules/plantings/add.php">Add a planting</a> first.
    </div>
<?php else: ?>
    <div class="card">
        <div class="card-body">
            <?php require __DIR__ . '/_form.php'; ?>
        </div>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
