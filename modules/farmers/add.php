<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/Farmer.php';
require_once __DIR__ . '/FarmerValidator.php';
require_login();

$farmerModel = new Farmer($pdo);
$validator   = new FarmerValidator($farmerModel);
$errors      = [];
$values      = [
    'first_name'      => '',
    'last_name'       => '',
    'contact_number'  => '',
    'address'         => '',
    'date_registered' => today_manila(),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf()) {
            $errors[] = 'Your session expired. Please try again.';
        } else {
            $result = $validator->validate($_POST);
            $errors = $result['errors'];
            $values = $result['values'];

            if (!$errors) {
                $farmerModel->create($result['clean']);
                set_flash('success', 'Farmer added successfully.');
                redirect('modules/farmers/index.php');
            }
        }
    } catch (PDOException $ex) {
        error_log($ex->getMessage());
        $errors[] = $ex->getCode() === '23505'
            ? 'Another farmer already uses this contact number.'
            : 'Something went wrong. Please try again.';
    }
}

$pageTitle   = 'Add farmer';
$submitLabel = 'Save farmer';
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h3 mb-3">Add farmer</h1>
<div class="card">
    <div class="card-body">
        <?php require __DIR__ . '/_form.php'; ?>
    </div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
