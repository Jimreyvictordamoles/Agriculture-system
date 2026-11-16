<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/Crop.php';
require_login();
require_once __DIR__ . '/CropValidator.php';

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$id) {
    set_flash('danger', 'Crop not found.');
    redirect('modules/crops/index.php');
}

$cropModel  = new Crop($pdo);
$validator  = new CropValidator($cropModel);
$errors     = [];
$categories = [];
$seasons    = [];
$values     = [];

try {
    $crop = $cropModel->findForEdit($id);

    if (!$crop) {
        set_flash('danger', 'Crop not found.');
        redirect('modules/crops/index.php');
    }

    $values = [
        'crop_name'           => (string) $crop['crop_name'],
        'variety'             => (string) ($crop['variety'] ?? ''),
        'category_id'         => (string) ($crop['category_id'] ?? ''),
        'season_id'           => (string) ($crop['season_id'] ?? ''),
        'growing_period_days' => (string) ($crop['growing_period_days'] ?? ''),
    ];

    $categories = $cropModel->getCategories();
    $seasons    = $cropModel->getSeasons();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verify_csrf()) {
            $errors[] = 'Your session expired. Please try again.';
        } else {
            $result = $validator->validate($_POST, $categories, $seasons, $id);
            $errors = $result['errors'];
            $values = $result['values'];

            if (!$errors) {
                $cropModel->update($id, $result['clean']);
                set_flash('success', 'Crop updated successfully.');
                redirect('modules/crops/index.php');
            }
        }
    }
} catch (PDOException $ex) {
    error_log($ex->getMessage());
    $errors[] = $ex->getCode() === '23505'
        ? 'A crop with the same name and variety already exists.'
        : 'Something went wrong. Please try again.';
    if (!$values) {
        $values = ['crop_name' => '', 'variety' => '', 'category_id' => '', 'season_id' => '', 'growing_period_days' => ''];
    }
}

$pageTitle   = 'Edit crop';
$submitLabel = 'Update crop';
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h3 mb-3">Edit crop</h1>
<div class="card">
    <div class="card-body">
        <?php require __DIR__ . '/_form.php'; ?>
    </div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
