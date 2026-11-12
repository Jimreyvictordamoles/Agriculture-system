<?php
require_once __DIR__ . '/../../includes/auth.php';
require_admin();   // only Administrators may delete

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
    set_flash('danger', 'Invalid request.');
    redirect('modules/plantings/index.php');
}

$id = filter_var($_POST['planting_id'] ?? null, FILTER_VALIDATE_INT);
if (!$id) {
    set_flash('danger', 'Planting not found.');
    redirect('modules/plantings/index.php');
}

try {
    $stmt = $pdo->prepare(
        'SELECT c.crop_name || \' on \' || fm.farm_name
         FROM planting p
         JOIN crop c ON c.crop_id = p.crop_id
         JOIN farm fm ON fm.farm_id = p.farm_id
         WHERE p.planting_id = :id'
    );
    $stmt->execute([':id' => $id]);
    $name = $stmt->fetchColumn();

    if ($name === false) {
        set_flash('danger', 'Planting not found.');
        redirect('modules/plantings/index.php');
    }

    // Referential integrity: a planting that has harvests cannot be deleted
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM harvest WHERE planting_id = :id');
    $stmt->execute([':id' => $id]);
    $used = (int) $stmt->fetchColumn();

    if ($used > 0) {
        set_flash('danger', 'Cannot delete the planting "' . $name . '": it has ' . $used
            . ' harvest record' . ($used === 1 ? '' : 's') . '. Delete those harvests first.');
        redirect('modules/plantings/index.php');
    }

    $stmt = $pdo->prepare('DELETE FROM planting WHERE planting_id = :id');
    $stmt->execute([':id' => $id]);
    set_flash('success', 'Planting "' . $name . '" was deleted.');
} catch (PDOException $ex) {
    error_log($ex->getMessage());
    if ($ex->getCode() === '23503') {   // foreign key violation (safety net)
        set_flash('danger', 'This planting is still in use and cannot be deleted.');
    } else {
        set_flash('danger', 'Could not delete the planting. Please try again.');
    }
}

redirect('modules/plantings/index.php');
