<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/_helpers.php';

$errors        = [];
$plantings     = [];
$grades        = [];
$remarks       = [];
$markHarvested = false;
$values        = [
    'planting_id'          => '',
    'harvest_date'         => today_manila(),
    'quantity_kg'          => '',
    'selling_price_per_kg' => '',
    'grade_id'             => '',
    'remark_id'            => '',
];

try {
    $plantings = fetch_planting_options($pdo);
    $grades    = fetch_lookup($pdo, 'quality_grade', 'grade_id', 'grade_name', true);
    $remarks   = fetch_lookup($pdo, 'harvest_remark', 'remark_id', 'remark_description', true);

    // Allows add.php?planting_id=5 to pre-select the planting (used by "Record harvest")
    $preselect = filter_var($_GET['planting_id'] ?? null, FILTER_VALIDATE_INT);
    if ($preselect && isset($plantings[$preselect])) {
        $values['planting_id'] = (string) $preselect;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $markHarvested = !empty($_POST['mark_harvested']);

        if (!verify_csrf()) {
            $errors[] = 'Your session expired. Please try again.';
        } else {
            $result = validate_harvest($_POST, $plantings, $grades, $remarks, $pdo);
            $errors = $result['errors'];
            $values = $result['values'];

            if (!$errors) {
                $c = $result['clean'];

                // Both changes succeed together or not at all
                $pdo->beginTransaction();

                $stmt = $pdo->prepare(
                    'INSERT INTO harvest (planting_id, grade_id, remark_id, harvest_date, quantity_kg, selling_price_per_kg)
                     VALUES (:planting_id, :grade_id, :remark_id, :harvest_date, :quantity, :price)'
                );
                $stmt->execute([
                    ':planting_id'  => $c['planting_id'],
                    ':grade_id'     => $c['grade_id'],
                    ':remark_id'    => $c['remark_id'],
                    ':harvest_date' => $c['harvest_date'],
                    ':quantity'     => $c['quantity_kg'],
                    ':price'        => $c['selling_price_per_kg'],
                ]);

                if ($markHarvested) {
                    $stmt = $pdo->query("SELECT status_id FROM planting_status WHERE status_name = 'Harvested'");
                    $harvestedId = $stmt->fetchColumn();
                    if ($harvestedId !== false) {
                        $stmt = $pdo->prepare('UPDATE planting SET status_id = :status_id WHERE planting_id = :planting_id');
                        $stmt->execute([':status_id' => $harvestedId, ':planting_id' => $c['planting_id']]);
                    }
                }

                $pdo->commit();
                set_flash('success', 'Harvest recorded successfully.');
                redirect('modules/harvests/index.php');
            }
        }
    }
} catch (PDOException $ex) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log($ex->getMessage());
    $errors[] = 'Something went wrong. Please try again.';
}

$pageTitle         = 'Add harvest';
$submitLabel       = 'Save harvest';
$showMarkHarvested = true;
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h3 mb-3">Add harvest</h1>
<?php if (!$plantings && !$errors): ?>
    <div class="alert alert-warning">
        A harvest belongs to a planting. <a href="<?= BASE_URL ?>modules/plantings/add.php">Add a planting</a> first.
    </div>
<?php else: ?>
    <div class="card">
        <div class="card-body">
            <?php require __DIR__ . '/_form.php'; ?>
        </div>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
