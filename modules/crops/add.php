<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/_helpers.php';

$errors = [];
$values = [
    'crop_name'           => '',
    'variety'             => '',
    'category_id'         => '',
    'season_id'           => '',
    'growing_period_days' => '',
];

try {
    $categories = fetch_lookup($pdo, 'crop_category', 'category_id', 'category_name');
    $seasons    = fetch_lookup($pdo, 'season', 'season_id', 'season_name');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verify_csrf()) {
            $errors[] = 'Your session expired. Please try again.';
        } else {
            $result = validate_crop($_POST, $categories, $seasons, $pdo);
            $errors = $result['errors'];
            $values = $result['values'];

            if (!$errors) {
                $c    = $result['clean'];
                $stmt = $pdo->prepare(
                    'INSERT INTO crop (crop_name, variety, category_id, season_id, growing_period_days)
                     VALUES (:name, :variety, :category_id, :season_id, :days)'
                );
                $stmt->execute([
                    ':name'        => $c['crop_name'],
                    ':variety'     => $c['variety'],
                    ':category_id' => $c['category_id'],
                    ':season_id'   => $c['season_id'],
                    ':days'        => $c['growing_period_days'],
                ]);
                set_flash('success', 'Crop added successfully.');
                redirect('modules/crops/index.php');
            }
        }
    }
} catch (PDOException $ex) {
    error_log($ex->getMessage());
    $categories = $categories ?? [];
    $seasons    = $seasons ?? [];
    $errors[]   = $ex->getCode() === '23505'
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
