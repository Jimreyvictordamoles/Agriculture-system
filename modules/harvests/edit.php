<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/HarvestValidator.php';
require_login();

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$id) {
    set_flash('danger', 'Harvest not found.');
    redirect('modules/harvests/index.php');
}

$harvestModel = new Harvest($pdo);
$validator    = new HarvestValidator($harvestModel);

$errors    = [];
$plantings = [];
$grades    = [];
$remarks   = [];
$values    = [
    'planting_id'          => '',
    'harvest_date'         => '',
    'quantity_kg'          => '',
    'selling_price_per_kg' => '',
    'grade_id'             => '',
    'remark_id'            => '',
];

try {
    $harvest = $harvestModel->findForEdit($id);

    if (!$harvest) {
        set_flash('danger', 'Harvest not found.');
        redirect('modules/harvests/index.php');
    }

    foreach ($values as $field => $unused) {
        $values[$field] = (string) ($harvest[$field] ?? '');
    }

    $plantings = $harvestModel->fetchPlantingOptions();
    $grades    = $harvestModel->fetchGrades();
    $remarks   = $harvestModel->fetchRemarks();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verify_csrf()) {
            $errors[] = 'Your session expired. Please try again.';
        } else {
            $result = $validator->validate($_POST, $plantings, $grades, $remarks, $id);
            $errors = $result['errors'];
            $values = $result['values'];

            if (!$errors) {
                $harvestModel->update($id, $result['clean']);
                set_flash('success', 'Harvest updated successfully.');
                redirect('modules/harvests/index.php');
            }
        }
    }
} catch (PDOException $ex) {
    error_log($ex->getMessage());
    $errors[] = 'Something went wrong. Please try again.';
}

$pageTitle   = 'Edit harvest';
$submitLabel = 'Update harvest';
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h3 mb-3">Edit harvest</h1>
<div class="card">
    <div class="card-body">
        <?php require __DIR__ . '/_form.php'; ?>
    </div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
