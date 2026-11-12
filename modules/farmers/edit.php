<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/_helpers.php';

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$id) {
    set_flash('danger', 'Farmer not found.');
    redirect('modules/farmers/index.php');
}

$errors = [];
$values = [
    'first_name'      => '',
    'last_name'       => '',
    'contact_number'  => '',
    'address'         => '',
    'date_registered' => '',
];

try {
    $stmt = $pdo->prepare(
        'SELECT first_name, last_name, contact_number, address, date_registered
         FROM farmer WHERE farmer_id = :id'
    );
    $stmt->execute([':id' => $id]);
    $farmer = $stmt->fetch(PDO::FETCH_ASSOC);

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
            $result = validate_farmer($_POST, $pdo, $id);
            $errors = $result['errors'];
            $values = $result['values'];

            if (!$errors) {
                $c    = $result['clean'];
                $stmt = $pdo->prepare(
                    'UPDATE farmer
                     SET first_name = :first_name, last_name = :last_name, contact_number = :contact,
                         address = :address, date_registered = :registered
                     WHERE farmer_id = :id'
                );
                $stmt->execute([
                    ':first_name' => $c['first_name'],
                    ':last_name'  => $c['last_name'],
                    ':contact'    => $c['contact_number'],
                    ':address'    => $c['address'],
                    ':registered' => $c['date_registered'],
                    ':id'         => $id,
                ]);
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
