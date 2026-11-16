<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/Farm.php';
require_once __DIR__ . '/FarmValidator.php';
require_login();

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$id) {
    set_flash('danger', 'Farm not found.');
    redirect('modules/farms/index.php');
}

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
    $farm = $farmModel->findForEdit($id);

    if (!$farm) {
        set_flash('danger', 'Farm not found.');
        redirect('modules/farms/index.php');
    }

    foreach ($values as $field => $unused) {
        $values[$field] = (string) ($farm[$field] ?? '');
    }

    $farmers         = $farmModel->fetchFarmerOptions();
    $soilTypes       = $farmModel->fetchSoilTypes();
    $irrigationTypes = $farmModel->fetchIrrigationTypes();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verify_csrf()) {
            $errors[] = 'Your session expired. Please try again.';
        } else {
            $result = $validator->validate($_POST, $farmers, $soilTypes, $irrigationTypes, $id);
            $errors = $result['errors'];
            $values = $result['values'];

            if (!$errors) {
                $farmModel->update($id, $result['clean']);
                set_flash('success', 'Farm updated successfully.');
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

$pageTitle   = 'Edit farm';
$submitLabel = 'Update farm';
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h3 mb-3">Edit farm</h1>
<div class="card">
    <div class="card-body">
        <?php require __DIR__ . '/_form.php'; ?>
    </div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
