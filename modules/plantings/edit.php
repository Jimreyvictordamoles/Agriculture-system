<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/_helpers.php';

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
    $stmt = $pdo->prepare(
        'SELECT farm_id, crop_id, status_id, planting_date, area_planted, seed_quantity, expected_harvest_date
         FROM planting WHERE planting_id = :id'
    );
    $stmt->execute([':id' => $id]);
    $planting = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$planting) {
        set_flash('danger', 'Planting not found.');
        redirect('modules/plantings/index.php');
    }

    foreach ($values as $field => $unused) {
        $values[$field] = (string) ($planting[$field] ?? '');
    }

    $farms    = fetch_farm_options($pdo);
    $crops    = fetch_crop_options($pdo);
    $statuses = fetch_lookup($pdo, 'planting_status', 'status_id', 'status_name', true);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verify_csrf()) {
            $errors[] = 'Your session expired. Please try again.';
        } else {
            $result = validate_planting($_POST, $farms, $crops, $statuses, $pdo, $id);
            $errors = $result['errors'];
            $values = $result['values'];

            if (!$errors) {
                $c    = $result['clean'];
                $stmt = $pdo->prepare(
                    'UPDATE planting
                     SET farm_id = :farm_id, crop_id = :crop_id, status_id = :status_id,
                         planting_date = :planting_date, area_planted = :area,
                         seed_quantity = :seed, expected_harvest_date = :expected
                     WHERE planting_id = :id'
                );
                $stmt->execute([
                    ':farm_id'       => $c['farm_id'],
                    ':crop_id'       => $c['crop_id'],
                    ':status_id'     => $c['status_id'],
                    ':planting_date' => $c['planting_date'],
                    ':area'          => $c['area_planted'],
                    ':seed'          => $c['seed_quantity'],
                    ':expected'      => $c['expected_harvest_date'],
                    ':id'            => $id,
                ]);
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
