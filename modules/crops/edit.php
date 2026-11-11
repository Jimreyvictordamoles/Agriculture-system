<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/_helpers.php';

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$id) {
    set_flash('danger', 'Crop not found.');
    redirect('modules/crops/index.php');
}

$errors     = [];
$categories = [];
$seasons    = [];
$values     = [];

try {
    $stmt = $pdo->prepare(
        'SELECT crop_name, variety, category_id, season_id, growing_period_days
         FROM crop WHERE crop_id = :id'
    );
    $stmt->execute([':id' => $id]);
    $crop = $stmt->fetch(PDO::FETCH_ASSOC);

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

    $categories = fetch_lookup($pdo, 'crop_category', 'category_id', 'category_name');
    $seasons    = fetch_lookup($pdo, 'season', 'season_id', 'season_name');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verify_csrf()) {
            $errors[] = 'Your session expired. Please try again.';
        } else {
            $result = validate_crop($_POST, $categories, $seasons, $pdo, $id);
            $errors = $result['errors'];
            $values = $result['values'];

            if (!$errors) {
                $c    = $result['clean'];
                $stmt = $pdo->prepare(
                    'UPDATE crop
                     SET crop_name = :name, variety = :variety, category_id = :category_id,
                         season_id = :season_id, growing_period_days = :days
                     WHERE crop_id = :id'
                );
                $stmt->execute([
                    ':name'        => $c['crop_name'],
                    ':variety'     => $c['variety'],
                    ':category_id' => $c['category_id'],
                    ':season_id'   => $c['season_id'],
                    ':days'        => $c['growing_period_days'],
                    ':id'          => $id,
                ]);
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
