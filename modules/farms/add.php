<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/_helpers.php';

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
    $farmers         = fetch_farmer_options($pdo);
    $soilTypes       = fetch_lookup($pdo, 'soil_type', 'soil_type_id', 'soil_type_name');
    $irrigationTypes = fetch_lookup($pdo, 'irrigation_type', 'irrigation_id', 'irrigation_name');

    // Allows add.php?farmer_id=5 to pre-select the owner
    $preselect = filter_var($_GET['farmer_id'] ?? null, FILTER_VALIDATE_INT);
    if ($preselect && isset($farmers[$preselect])) {
        $values['farmer_id'] = (string) $preselect;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verify_csrf()) {
            $errors[] = 'Your session expired. Please try again.';
        } else {
            $result = validate_farm($_POST, $farmers, $soilTypes, $irrigationTypes, $pdo);
            $errors = $result['errors'];
            $values = $result['values'];

            if (!$errors) {
                $c    = $result['clean'];
                $stmt = $pdo->prepare(
                    'INSERT INTO farm (farmer_id, farm_name, location, area_hectares, soil_type_id, irrigation_id)
                     VALUES (:farmer_id, :farm_name, :location, :area, :soil_type_id, :irrigation_id)'
                );
                $stmt->execute([
                    ':farmer_id'     => $c['farmer_id'],
                    ':farm_name'     => $c['farm_name'],
                    ':location'      => $c['location'],
                    ':area'          => $c['area_hectares'],
                    ':soil_type_id'  => $c['soil_type_id'],
                    ':irrigation_id' => $c['irrigation_id'],
                ]);
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
