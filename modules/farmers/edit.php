<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/Farmer.php';
require_once __DIR__ . '/FarmerValidator.php';
require_login();

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$id) {
    set_flash('danger', 'Farmer not found.');
    redirect('modules/farmers/index.php');
}

$farmerModel = new Farmer($pdo);
$validator   = new FarmerValidator($farmerModel);
$errors      = [];
$values      = [
    'first_name'      => '',
    'last_name'       => '',
    'contact_number'  => '',
    'address'         => '',
    'date_registered' => '',
];

try {
    $farmer = $farmerModel->findForEdit($id);

    if (!$farmer) {
        set_flash('danger', 'Farmer not found.');
        redirect('modules/farmers/index.php');
    }

    foreach ($values as $field => $unused) {
        $values[$field] = (string) ($farmer[$field] ?? '');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verify_csrf()) {
            $errors[] = 'Your session expired. Please try again.';
        } else {
            $result = $validator->validate($_POST, $id);
            $errors = $result['errors'];
            $values = $result['values'];

            if (!$errors) {
                $farmerModel->update($id, $result['clean']);
                set_flash('success', 'Farmer updated successfully.');
                redirect('modules/farmers/index.php');
            }
        }
    }
} catch (PDOException $ex) {
    error_log($ex->getMessage());
    $errors[] = $ex->getCode() === '23505'
        ? 'Another farmer already uses this contact number.'
        : 'Something went wrong. Please try again.';
}

$pageTitle   = 'Edit farmer';
$submitLabel = 'Update farmer';
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h3 mb-3">Edit farmer</h1>
<div class="card">
    <div class="card-body">
        <?php require __DIR__ . '/_form.php'; ?>
    </div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
