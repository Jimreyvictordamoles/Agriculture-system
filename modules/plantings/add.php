<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();

require_once __DIR__ . '/Planting.php';
require_once __DIR__ . '/PlantingValidator.php';

$plantings = new Planting($pdo);

$errors   = [];
$farms    = [];
$crops    = [];
$statuses = [];
$values   = [
    'farm_id'               => '',
    'crop_id'               => '',
    'status_id'             => '',
    'planting_date'         => today_manila(),
    'area_planted'          => '',
    'seed_quantity'         => '',
    'expected_harvest_date' => '',
];

try {
    $farms    = $plantings->fetchFarmOptions();
    $crops    = $plantings->fetchCropOptions();
    $statuses = $plantings->fetchStatuses();

    // New plantings start as "Planted"
    $plantedId = array_search('Planted', $statuses, true);
    if ($plantedId !== false) {
        $values['status_id'] = (string) $plantedId;
    }

    // Allows add.php?farm_id=5 to pre-select the farm
    $preselect = filter_var($_GET['farm_id'] ?? null, FILTER_VALIDATE_INT);
    if ($preselect && isset($farms[$preselect])) {
        $values['farm_id'] = (string) $preselect;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verify_csrf()) {
            $errors[] = 'Your session expired. Please try again.';
        } else {
            $validator = new PlantingValidator($plantings);
            $result    = $validator->validate($_POST, $farms, $crops, $statuses);
            $errors = $result['errors'];
            $values = $result['values'];

            if (!$errors) {
                $plantings->create($result['clean']);
                set_flash('success', 'Planting added successfully.');
                redirect('modules/plantings/index.php');
            }
        }
    }
} catch (PDOException $ex) {
    error_log($ex->getMessage());
    $errors[] = 'Something went wrong. Please try again.';
}

$pageTitle   = 'Add planting';
$submitLabel = 'Save planting';
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h3 mb-3">Add planting</h1>
<?php if ((!$farms || !$crops) && !$errors): ?>
    <div class="alert alert-warning">
        A planting needs a farm and a crop.
        <?php if (!$farms): ?>
            <a href="<?= BASE_URL ?>modules/farms/add.php">Add a farm</a>.
        <?php endif; ?>
        <?php if (!$crops): ?>
            <a href="<?= BASE_URL ?>modules/crops/add.php">Add a crop</a>.
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="card">
        <div class="card-body">
            <?php require __DIR__ . '/_form.php'; ?>
        </div>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
