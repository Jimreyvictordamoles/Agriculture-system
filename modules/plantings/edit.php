<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();

require_once __DIR__ . '/Planting.php';
require_once __DIR__ . '/PlantingValidator.php';

$plantings = new Planting($pdo);

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$id) {
    set_flash('danger', 'Planting not found.');
    redirect('modules/plantings/index.php');
}

$errors   = [];
$farms    = [];
$crops    = [];
$statuses = [];
$values   = [
    'farm_id'               => '',
    'crop_id'               => '',
    'status_id'             => '',
    'planting_date'         => '',
    'area_planted'          => '',
    'seed_quantity'         => '',
    'expected_harvest_date' => '',
];

try {
    $planting = $plantings->findForEdit($id);

    if (!$planting) {
        set_flash('danger', 'Planting not found.');
        redirect('modules/plantings/index.php');
    }

    foreach ($values as $field => $unused) {
        $values[$field] = (string) ($planting[$field] ?? '');
    }

    $farms    = $plantings->fetchFarmOptions();
    $crops    = $plantings->fetchCropOptions();
    $statuses = $plantings->fetchStatuses();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verify_csrf()) {
            $errors[] = 'Your session expired. Please try again.';
        } else {
            $validator = new PlantingValidator($plantings);
            $result    = $validator->validate($_POST, $farms, $crops, $statuses, $id);
            $errors = $result['errors'];
            $values = $result['values'];

            if (!$errors) {
                $plantings->update($id, $result['clean']);
                set_flash('success', 'Planting updated successfully.');
                redirect('modules/plantings/index.php');
            }
        }
    }
} catch (PDOException $ex) {
    error_log($ex->getMessage());
    $errors[] = $ex->getCode() === '23514'
        ? 'One of the values breaks a database rule. Please check the dates and numbers.'
        : 'Something went wrong. Please try again.';
}

$pageTitle   = 'Edit planting';
$submitLabel = 'Update planting';
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h3 mb-3">Edit planting</h1>
<div class="card">
    <div class="card-body">
        <?php require __DIR__ . '/_form.php'; ?>
    </div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
