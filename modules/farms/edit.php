<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/_helpers.php';

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$id) {
    set_flash('danger', 'Farm not found.');
    redirect('modules/farms/index.php');
}

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
    $stmt = $pdo->prepare(
        'SELECT farmer_id, farm_name, location, area_hectares, soil_type_id, irrigation_id
         FROM farm WHERE farm_id = :id'
    );
    $stmt->execute([':id' => $id]);
    $farm = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$farm) {
        set_flash('danger', 'Farm not found.');
        redirect('modules/farms/index.php');
    }

    foreach ($values as $field => $unused) {
        $values[$field] = (string) ($farm[$field] ?? '');
    }

    $farmers         = fetch_farmer_options($pdo);
    $soilTypes       = fetch_lookup($pdo, 'soil_type', 'soil_type_id', 'soil_type_name');
    $irrigationTypes = fetch_lookup($pdo, 'irrigation_type', 'irrigation_id', 'irrigation_name');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verify_csrf()) {
            $errors[] = 'Your session expired. Please try again.';
        } else {
            $result = validate_farm($_POST, $farmers, $soilTypes, $irrigationTypes, $pdo, $id);
            $errors = $result['errors'];
            $values = $result['values'];

            if (!$errors) {
                $c    = $result['clean'];
                $stmt = $pdo->prepare(
                    'UPDATE farm
                     SET farmer_id = :farmer_id, farm_name = :farm_name, location = :location,
                         area_hectares = :area, soil_type_id = :soil_type_id, irrigation_id = :irrigation_id
                     WHERE farm_id = :id'
                );
                $stmt->execute([
                    ':farmer_id'     => $c['farmer_id'],
                    ':farm_name'     => $c['farm_name'],
                    ':location'      => $c['location'],
                    ':area'          => $c['area_hectares'],
                    ':soil_type_id'  => $c['soil_type_id'],
                    ':irrigation_id' => $c['irrigation_id'],
                    ':id'            => $id,
                ]);
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
