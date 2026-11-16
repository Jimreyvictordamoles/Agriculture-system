<?php
// Quick actions from the users list: approve / reactivate, deactivate, remove.
require_once __DIR__ . '/../../includes/auth.php';
require_admin();
require_once __DIR__ . '/User.php';

$userModel = new User($pdo);

// Send the Administrator back to the same search / filter / page they were on
$back = 'modules/users/index.php' . users_back_query((string) ($_POST['back'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
    set_flash('danger', 'Invalid request.');
    redirect($back);
}

$userId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
$do     = (string) ($_POST['do'] ?? '');
if (!$userId || !in_array($do, ['activate', 'deactivate', 'remove'], true)) {
    set_flash('danger', 'Invalid request.');
    redirect($back);
}

try {
    $stmt = $pdo->prepare('SELECT full_name, is_active, approved_at FROM users WHERE user_id = :id');
    $stmt->execute([':id' => $userId]);
    $target = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $ex) {
    error_log($ex->getMessage());
    set_flash('danger', 'Something went wrong. Please try again.');
    redirect($back);
}
if (!$target) {
    set_flash('danger', 'User not found.');
    redirect($back);
}

$name    = '"' . $target['full_name'] . '"';
$status  = user_status($target);
$actorId = (int) current_user()['user_id'];
$error   = null;
$done    = '';

switch ($do) {
    case 'activate':
        $error = $status === 'active' ? 'This account is already active.'
               : $userModel->change((int) $userId, $actorId, null, true);
        $done  = $status === 'pending'
            ? "$name was approved and can now log in."
            : "$name was reactivated and can log in again.";
        break;

    case 'deactivate':
        $error = $status !== 'active' ? 'This account is not active.'
               : $userModel->change((int) $userId, $actorId, null, false);
        $done  = "$name was deactivated and can no longer log in.";
        break;

    case 'remove':
        $error = $userModel->remove((int) $userId, $actorId);
        $done  = $status === 'pending'
            ? "The sign-up request from $name was rejected and removed."
            : "$name was removed.";
        break;
}

set_flash($error ? 'danger' : 'success', $error ?? $done);
redirect($back);
