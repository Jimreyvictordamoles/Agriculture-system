<?php
require_once __DIR__ . '/../../includes/auth.php';
require_admin();
require_once __DIR__ . '/_helpers.php';

$roles    = user_roles($pdo);
$staffId  = array_search('Staff', $roles, true);
$errors   = [];
$values   = ['full_name' => '', 'username' => '', 'role_id' => $staffId === false ? '' : (string) $staffId, 'is_active' => '1'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $result = validate_new_user($_POST, $roles);
        $errors = $result['errors'];
        $values = $result['values'];

        if (!$errors) {
            $c = $result['clean'];
            try {
                $stmt = $pdo->prepare(
                    'INSERT INTO users (role_id, username, password_hash, full_name, is_active, approved_at)
                     VALUES (:role, :username, :hash, :name, CAST(:active AS BOOLEAN), NOW())'
                );
                $stmt->execute([
                    ':role'     => $c['role_id'],
                    ':username' => $c['username'],
                    ':hash'     => password_hash($c['password'], PASSWORD_DEFAULT),
                    ':name'     => $c['full_name'],
                    ':active'   => $c['is_active'] ? 't' : 'f',
                ]);
                set_flash('success', 'User "' . $c['username'] . '" was created.');
                redirect('modules/users/index.php');
            } catch (PDOException $ex) {
                if ($ex->getCode() === '23505') {
                    $errors[] = 'That username is already taken.';
                } else {
                    error_log($ex->getMessage());
                    $errors[] = 'Something went wrong. Please try again.';
                }
            }
        }
    }
}

$pageTitle = 'Add user';
require __DIR__ . '/../../includes/header.php';
?>
<a href="index.php" class="text-decoration-none small">&larr; Back to users</a>
<h1 class="h3 mt-1 mb-3">Add user</h1>
<div class="card">
    <div class="card-body">
        <?php foreach ($errors as $error): ?>
            <div class="alert alert-danger py-2"><?= e($error) ?></div>
        <?php endforeach; ?>

        <form method="post" action="">
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="full_name" class="form-label">Full name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="full_name" name="full_name"
                           value="<?= e($values['full_name']) ?>" maxlength="100" required autofocus>
                </div>
                <div class="col-md-6">
                    <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="username" name="username"
                           value="<?= e($values['username']) ?>" maxlength="50" required>
                </div>
                <div class="col-md-6">
                    <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                    <input type="password" class="form-control" id="password" name="password"
                           minlength="8" maxlength="72" required>
                    <div class="form-text">At least 8 characters.</div>
                </div>
                <div class="col-md-6">
                    <label for="confirm_password" class="form-label">Confirm password <span class="text-danger">*</span></label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                           minlength="8" maxlength="72" required>
                </div>
                <div class="col-md-6">
                    <label for="role_id" class="form-label">Role <span class="text-danger">*</span></label>
                    <select class="form-select" id="role_id" name="role_id" required>
                        <?php foreach ($roles as $id => $name): ?>
                            <option value="<?= (int) $id ?>" <?= (string) $id === $values['role_id'] ? 'selected' : '' ?>><?= e($name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                               <?= $values['is_active'] === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">Active (can log in right away)</label>
                        <div class="form-text mt-0">Untick to create the account as deactivated.</div>
                    </div>
                </div>
            </div>
            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-success">Create user</button>
                <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
