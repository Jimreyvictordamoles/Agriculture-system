<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/_helpers.php';

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$id) {
    set_flash('danger', 'Harvest not found.');
    redirect('modules/harvests/index.php');
}

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
    $stmt = $pdo->prepare(
        'SELECT planting_id, harvest_date, quantity_kg, selling_price_per_kg, grade_id, remark_id
         FROM harvest WHERE harvest_id = :id'
    );
    $stmt->execute([':id' => $id]);
    $harvest = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$harvest) {
        set_flash('danger', 'Harvest not found.');
        redirect('modules/harvests/index.php');
    }

    foreach ($values as $field => $unused) {
        $values[$field] = (string) ($harvest[$field] ?? '');
    }

    $plantings = fetch_planting_options($pdo);
    $grades    = fetch_lookup($pdo, 'quality_grade', 'grade_id', 'grade_name', true);
    $remarks   = fetch_lookup($pdo, 'harvest_remark', 'remark_id', 'remark_description', true);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verify_csrf()) {
            $errors[] = 'Your session expired. Please try again.';
        } else {
            $result = validate_harvest($_POST, $plantings, $grades, $remarks, $pdo, $id);
            $errors = $result['errors'];
            $values = $result['values'];

            if (!$errors) {
                $c    = $result['clean'];
                $stmt = $pdo->prepare(
                    'UPDATE harvest
                     SET planting_id = :planting_id, grade_id = :grade_id, remark_id = :remark_id,
                         harvest_date = :harvest_date, quantity_kg = :quantity,
                         selling_price_per_kg = :price
                     WHERE harvest_id = :id'
                );
                $stmt->execute([
                    ':planting_id'  => $c['planting_id'],
                    ':grade_id'     => $c['grade_id'],
                    ':remark_id'    => $c['remark_id'],
                    ':harvest_date' => $c['harvest_date'],
                    ':quantity'     => $c['quantity_kg'],
                    ':price'        => $c['selling_price_per_kg'],
                    ':id'           => $id,
                ]);
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
