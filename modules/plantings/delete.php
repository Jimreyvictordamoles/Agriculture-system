<?php
require_once __DIR__ . '/../../includes/auth.php';
require_admin();   // only Administrators may delete

require_once __DIR__ . '/Planting.php';

$plantings = new Planting($pdo);

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
    $name = $plantings->findLabel($id);

    if ($name === null) {
        set_flash('danger', 'Planting not found.');
        redirect('modules/plantings/index.php');
    }

    // Referential integrity: a planting that has harvests cannot be deleted
    $used = $plantings->countHarvests($id);

    if ($used > 0) {
        set_flash('danger', 'Cannot delete the planting "' . $name . '": it has ' . $used
            . ' harvest record' . ($used === 1 ? '' : 's') . '. Delete those harvests first.');
        redirect('modules/plantings/index.php');
    }

    $plantings->delete($id);
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
