<?php
require_once __DIR__ . '/../../includes/auth.php';
require_admin();   // only Administrators may delete

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
    set_flash('danger', 'Invalid request.');
    redirect('modules/harvests/index.php');
}

$id = filter_var($_POST['harvest_id'] ?? null, FILTER_VALIDATE_INT);
if (!$id) {
    set_flash('danger', 'Harvest not found.');
    redirect('modules/harvests/index.php');
}

try {
    $stmt = $pdo->prepare('SELECT 1 FROM harvest WHERE harvest_id = :id');
    $stmt->execute([':id' => $id]);

    if (!$stmt->fetchColumn()) {
        set_flash('danger', 'Harvest not found.');
        redirect('modules/harvests/index.php');
    }

    // Nothing else depends on a harvest record, so it can be deleted directly
    $stmt = $pdo->prepare('DELETE FROM harvest WHERE harvest_id = :id');
    $stmt->execute([':id' => $id]);
    set_flash('success', 'Harvest record was deleted.');
} catch (PDOException $ex) {
    error_log($ex->getMessage());
    set_flash('danger', 'Could not delete the harvest. Please try again.');
}

redirect('modules/harvests/index.php');
