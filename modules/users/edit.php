<?php
require_once __DIR__ . '/../../includes/auth.php';
require_admin();
require_once __DIR__ . '/User.php';

$userModel = new User($pdo);

$id      = filter_var($_GET['id'] ?? ($_POST['user_id'] ?? null), FILTER_VALIDATE_INT);
$account = null;
if ($id) {
    try {
        $account = $userModel->find((int) $id);
    } catch (PDOException $ex) {
        error_log($ex->getMessage());
    }
}
if (!$account) {
    set_flash('danger', 'User not found.');
    redirect('modules/users/index.php');
}

$roles    = $userModel->fetchRoles();
$actorId  = (int) current_user()['user_id'];
$isSelf   = (int) $account['user_id'] === $actorId;
$state    = user_status($account);   // active, pending or deactivated
$errors   = [];
$values   = [
    'full_name' => $account['full_name'],
    'role_id'   => (string) $account['role_id'],
    'is_active' => db_true($account['is_active']) ? '1' : '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values = [
        'full_name' => trim((string) ($_POST['full_name'] ?? '')),
        'role_id'   => trim((string) ($_POST['role_id'] ?? '')),
        'is_active' => isset($_POST['is_active']) ? '1' : '',
    ];
    $roleId = filter_var($values['role_id'], FILTER_VALIDATE_INT);

    if (!verify_csrf()) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        if ($values['full_name'] === '' || mb_strlen($values['full_name']) > 100) {
            $errors[] = 'Full name is required (maximum 100 characters).';
        }
        if ($roleId === false || !isset($roles[$roleId])) {
            $errors[] = 'Please choose a role.';
        }
        if (!$errors) {
            $error = $userModel->change(
                (int) $account['user_id'],
                $actorId,
                (int) $roleId,
                $values['is_active'] === '1',
                $values['full_name']
            );
            if ($error) {
                $errors[] = $error;
            } else {
                set_flash('success', 'User "' . $account['username'] . '" was updated.');
                redirect('modules/users/index.php');
            }
        }
    }
}

$pageTitle = 'Edit user';
require __DIR__ . '/../../includes/header.php';
?>
<a href="index.php" class="text-decoration-none small">&larr; Back to users</a>
<div class="d-flex align-items-center gap-2 mt-1 mb-3">
    <h1 class="h3 mb-0">Edit user</h1>
    <?php if ($state === 'active'): ?>
        <span class="badge rounded-pill text-bg-success">Active</span>
    <?php elseif ($state === 'pending'): ?>
        <span class="badge rounded-pill text-bg-warning">Pending approval</span>
    <?php else: ?>
        <span class="badge rounded-pill text-bg-secondary">Deactivated</span>
    <?php endif; ?>
</div>
<div class="card">
    <div class="card-body">
        <?php foreach ($errors as $err): ?>
            <div class="alert alert-danger py-2"><?= e($err) ?></div>
        <?php endforeach; ?>

        <form method="post" action="">
            <?= csrf_field() ?>
            <input type="hidden" name="user_id" value="<?= (int) $account['user_id'] ?>">
            <?php if ($isSelf): ?>
                <?php /* Your own role and status are locked; these hidden fields keep their current values */ ?>
                <input type="hidden" name="role_id" value="<?= e($values['role_id']) ?>">
                <input type="hidden" name="is_active" value="1">
            <?php endif; ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Username</label>
                    <input type="text" class="form-control" value="<?= e($account['username']) ?>" disabled>
                </div>
                <div class="col-md-6">
                    <label for="full_name" class="form-label">Full name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="full_name" name="full_name"
                           value="<?= e($values['full_name']) ?>" maxlength="100" required autofocus>
                </div>
                <div class="col-md-6">
                    <label for="role_id" class="form-label">Role <span class="text-danger">*</span></label>
                    <select class="form-select" id="role_id" name="role_id" required <?= $isSelf ? 'disabled' : '' ?>>
                        <?php foreach ($roles as $rid => $name): ?>
                            <option value="<?= (int) $rid ?>" <?= (string) $rid === $values['role_id'] ? 'selected' : '' ?>><?= e($name) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($isSelf): ?><div class="form-text">You cannot change your own role.</div><?php endif; ?>
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                               <?= $values['is_active'] === '1' ? 'checked' : '' ?> <?= $isSelf ? 'disabled' : '' ?>>
                        <label class="form-check-label" for="is_active">Active (allowed to log in)</label>
                        <div class="form-text mt-0">
                            <?php if ($isSelf): ?>
                                You cannot deactivate your own account.
                            <?php elseif ($state === 'pending'): ?>
                                Tick to approve this sign-up.
                            <?php elseif ($state === 'deactivated'): ?>
                                Tick to reactivate this account.
                            <?php else: ?>
                                Untick to deactivate this account.
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <p class="text-muted small mt-3 mb-0">
                Created <?= e(format_datetime($account['created_at'])) ?>
                <?php if (!empty($account['approved_at'])): ?> &middot; Approved <?= e(format_datetime($account['approved_at'])) ?><?php endif; ?>
            </p>
            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-success">Save changes</button>
                <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
