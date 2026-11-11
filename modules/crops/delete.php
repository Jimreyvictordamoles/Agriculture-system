<?php
require_once __DIR__ . '/../../includes/auth.php';
require_admin();   // only Administrators may delete

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
    set_flash('danger', 'Invalid request.');
    redirect('modules/crops/index.php');
}

$id = filter_var($_POST['crop_id'] ?? null, FILTER_VALIDATE_INT);
if (!$id) {
    set_flash('danger', 'Crop not found.');
    redirect('modules/crops/index.php');
}

try {
    $stmt = $pdo->prepare('SELECT crop_name FROM crop WHERE crop_id = :id');
    $stmt->execute([':id' => $id]);
    $name = $stmt->fetchColumn();

    if ($name === false) {
        set_flash('danger', 'Crop not found.');
        redirect('modules/crops/index.php');
    }

    // Referential integrity: a crop that is used by plantings cannot be deleted
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM planting WHERE crop_id = :id');
    $stmt->execute([':id' => $id]);
    $used = (int) $stmt->fetchColumn();

    if ($used > 0) {
        set_flash('danger', 'Cannot delete "' . $name . '": it is used by ' . $used
            . ' planting record' . ($used === 1 ? '' : 's') . '. Remove or change those plantings first.');
        redirect('modules/crops/index.php');
    }

    $stmt = $pdo->prepare('DELETE FROM crop WHERE crop_id = :id');
    $stmt->execute([':id' => $id]);
    set_flash('success', 'Crop "' . $name . '" was deleted.');
} catch (PDOException $ex) {
    error_log($ex->getMessage());
    if ($ex->getCode() === '23503') {   // foreign key violation (safety net)
        set_flash('danger', 'This crop is still in use and cannot be deleted.');
    } else {
        set_flash('danger', 'Could not delete the crop. Please try again.');
    }
}

redirect('modules/crops/index.php');
