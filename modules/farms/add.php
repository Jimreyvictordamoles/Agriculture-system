<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/Farm.php';
require_once __DIR__ . '/FarmValidator.php';
require_login();

$farmModel       = new Farm($pdo);
$validator       = new FarmValidator($farmModel);
$errors          = [];
$farmers         = [];
$soilTypes       = [];
$irrigationTypes = [];
$values          = [
    'farmer_id'     => '',
    'farm_name'     => '',
    'location'      => '',
    'area_hectares' => '',
    'soil_type_id'  => '',
    'irrigation_id' => '',
];

try {
    $farmers         = $farmModel->fetchFarmerOptions();
    $soilTypes       = $farmModel->fetchSoilTypes();
    $irrigationTypes = $farmModel->fetchIrrigationTypes();

    // Allows add.php?farmer_id=5 to pre-select the owner
    $preselect = filter_var($_GET['farmer_id'] ?? null, FILTER_VALIDATE_INT);
    if ($preselect && isset($farmers[$preselect])) {
        $values['farmer_id'] = (string) $preselect;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verify_csrf()) {
            $errors[] = 'Your session expired. Please try again.';
        } else {
            $result = $validator->validate($_POST, $farmers, $soilTypes, $irrigationTypes);
            $errors = $result['errors'];
            $values = $result['values'];

            if (!$errors) {
                $farmModel->create($result['clean']);
                set_flash('success', 'Farm added successfully.');
                redirect('modules/farms/index.php');
            }
        }
    }
} catch (PDOException $ex) {
    error_log($ex->getMessage());
    $errors[] = $ex->getCode() === '23505'
        ? 'This farmer already has a farm with that name.'
        : 'Something went wrong. Please try again.';
}

$pageTitle   = 'Add farm';
$submitLabel = 'Save farm';
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h3 mb-3">Add farm</h1>
<?php if (!$farmers && !$errors): ?>
    <div class="alert alert-warning">
        There are no farmers yet. <a href="<?= BASE_URL ?>modules/farmers/add.php">Add a farmer</a>
        first, because every farm needs an owner.
    </div>
<?php else: ?>
    <div class="card">
        <div class="card-body">
            <?php require __DIR__ . '/_form.php'; ?>
        </div>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
