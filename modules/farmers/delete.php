<?php
require_once __DIR__ . '/../../includes/auth.php';
require_admin();   // only Administrators may delete

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
    set_flash('danger', 'Invalid request.');
    redirect('modules/farmers/index.php');
}

$id = filter_var($_POST['farmer_id'] ?? null, FILTER_VALIDATE_INT);
if (!$id) {
    set_flash('danger', 'Farmer not found.');
    redirect('modules/farmers/index.php');
}

try {
    $stmt = $pdo->prepare("SELECT first_name || ' ' || last_name FROM farmer WHERE farmer_id = :id");
    $stmt->execute([':id' => $id]);
    $name = $stmt->fetchColumn();

    if ($name === false) {
        set_flash('danger', 'Farmer not found.');
        redirect('modules/farmers/index.php');
    }

    // Referential integrity: a farmer who still has farms cannot be deleted
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM farm WHERE farmer_id = :id');
    $stmt->execute([':id' => $id]);
    $used = (int) $stmt->fetchColumn();

    if ($used > 0) {
        set_flash('danger', 'Cannot delete "' . $name . '": this farmer still has ' . $used
            . ' farm' . ($used === 1 ? '' : 's') . '. Delete or reassign those farms first.');
        redirect('modules/farmers/index.php');
    }

    $stmt = $pdo->prepare('DELETE FROM farmer WHERE farmer_id = :id');
    $stmt->execute([':id' => $id]);
    set_flash('success', 'Farmer "' . $name . '" was deleted.');
} catch (PDOException $ex) {
    error_log($ex->getMessage());
    if ($ex->getCode() === '23503') {   // foreign key violation (safety net)
        set_flash('danger', 'This farmer is still in use and cannot be deleted.');
    } else {
        set_flash('danger', 'Could not delete the farmer. Please try again.');
    }
}

redirect('modules/farmers/index.php');
