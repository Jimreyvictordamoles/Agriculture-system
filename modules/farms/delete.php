<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/Farm.php';
require_admin();   // only Administrators may delete

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
    set_flash('danger', 'Invalid request.');
    redirect('modules/farms/index.php');
}

$id = filter_var($_POST['farm_id'] ?? null, FILTER_VALIDATE_INT);
if (!$id) {
    set_flash('danger', 'Farm not found.');
    redirect('modules/farms/index.php');
}

$farmModel = new Farm($pdo);

try {
    $name = $farmModel->getName($id);

    if ($name === null) {
        set_flash('danger', 'Farm not found.');
        redirect('modules/farms/index.php');
    }

    // Referential integrity: a farm that still has plantings cannot be deleted
    $used = $farmModel->countPlantings($id);

    if ($used > 0) {
        set_flash('danger', 'Cannot delete "' . $name . '": it still has ' . $used
            . ' planting record' . ($used === 1 ? '' : 's') . '. Delete those plantings first.');
        redirect('modules/farms/index.php');
    }

    $farmModel->delete($id);
    set_flash('success', 'Farm "' . $name . '" was deleted.');
} catch (PDOException $ex) {
    error_log($ex->getMessage());
    if ($ex->getCode() === '23503') {   // foreign key violation (safety net)
        set_flash('danger', 'This farm is still in use and cannot be deleted.');
    } else {
        set_flash('danger', 'Could not delete the farm. Please try again.');
    }
}

redirect('modules/farms/index.php');
