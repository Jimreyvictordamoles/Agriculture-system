<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/Crop.php';
require_login();
require_once __DIR__ . '/CropValidator.php';

$cropModel  = new Crop($pdo);
$validator  = new CropValidator($cropModel);
$errors     = [];
$categories = [];
$seasons    = [];
$values     = [
    'crop_name'           => '',
    'variety'             => '',
    'category_id'         => '',
    'season_id'           => '',
    'growing_period_days' => '',
];

try {
    $categories = $cropModel->getCategories();
    $seasons    = $cropModel->getSeasons();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verify_csrf()) {
            $errors[] = 'Your session expired. Please try again.';
        } else {
            $result = $validator->validate($_POST, $categories, $seasons);
            $errors = $result['errors'];
            $values = $result['values'];

            if (!$errors) {
                $cropModel->create($result['clean']);
                set_flash('success', 'Crop added successfully.');
                redirect('modules/crops/index.php');
            }
        }
    }
} catch (PDOException $ex) {
    error_log($ex->getMessage());
    $errors[] = $ex->getCode() === '23505'
        ? 'A crop with the same name and variety already exists.'
        : 'Something went wrong. Please try again.';
}

$pageTitle   = 'Add crop';
$submitLabel = 'Save crop';
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h3 mb-3">Add crop</h1>
<div class="card">
    <div class="card-body">
        <?php require __DIR__ . '/_form.php'; ?>
    </div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
