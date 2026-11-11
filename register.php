<?php
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

$errors   = [];
$fullName = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (!verify_csrf()) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        if ($fullName === '' || mb_strlen($fullName) > 100) {
            $errors[] = 'Full name is required (maximum 100 characters).';
        }
        if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
            $errors[] = 'Username must be 3 to 50 characters: letters, numbers, dot, dash or underscore.';
        }
        if (strlen($password) < 8 || strlen($password) > 72) {
            $errors[] = 'Password must be between 8 and 72 characters.';
        }
        if ($password !== $confirm) {
            $errors[] = 'The passwords do not match.';
        }
    }

    if (!$errors) {
        try {
            // The role is set here in the code and is never read from the form,
            // so a visitor cannot sign up as an Administrator.
            $stmt = $pdo->prepare(
                "INSERT INTO users (role_id, username, password_hash, full_name)
                 VALUES ((SELECT role_id FROM user_role WHERE role_name = 'Staff'),
                         :username, :hash, :full_name)"
            );
            $stmt->execute([
                ':username'  => $username,
                ':hash'      => password_hash($password, PASSWORD_DEFAULT),
                ':full_name' => $fullName,
            ]);
            set_flash('success', 'Account created successfully! Please log in.');
            redirect('login.php');
        } catch (PDOException $ex) {
            if ($ex->getCode() === '23505') {
                $errors[] = 'That username is already taken. If you just created this account, go to the login page and use it.';
            } else {
                error_log($ex->getMessage());
                $errors[] = 'Something went wrong. Please try again.';
            }
        }
    }
}

$pageTitle = 'Sign up';
require __DIR__ . '/includes/header.php';
?>
<div class="card shadow-sm auth-card">
    <div class="card-body p-4">
        <h1 class="h4 mb-1">Create an account</h1>
        <p class="text-muted mb-4">New accounts are created with the Staff role.</p>

        <?php foreach ($errors as $error): ?>
            <div class="alert alert-danger py-2"><?= e($error) ?></div>
        <?php endforeach; ?>

        <form method="post" action="" autocomplete="on">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label for="full_name" class="form-label">Full name</label>
                <input type="text" class="form-control" id="full_name" name="full_name"
                       value="<?= e($fullName) ?>" maxlength="100" required autofocus>
            </div>
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" class="form-control" id="username" name="username"
                       value="<?= e($username) ?>" maxlength="50" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password"
                       minlength="8" maxlength="72" required>
                <div class="form-text">At least 8 characters.</div>
            </div>
            <div class="mb-3">
                <label for="confirm_password" class="form-label">Confirm password</label>
                <input type="password" class="form-control" id="confirm_password"
                       name="confirm_password" minlength="8" maxlength="72" required>
            </div>
            <button type="submit" class="btn btn-success w-100">Sign up</button>
        </form>

        <p class="text-center mt-3 mb-0">
            Already have an account? <a href="<?= BASE_URL ?>login.php">Log in</a>
        </p>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
