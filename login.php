<?php
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

$errors   = [];
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!verify_csrf()) {
        $errors[] = 'Your session expired. Please try again.';
    } elseif ($username === '' || $password === '') {
        $errors[] = 'Enter your username and password.';
    } else {
        $row = false;
        try {
            $stmt = $pdo->prepare(
                'SELECT u.user_id, u.username, u.full_name, u.password_hash,
                        u.is_active, u.role_id, r.role_name
                 FROM users u
                 JOIN user_role r ON r.role_id = u.role_id
                 WHERE u.username = :username'
            );
            $stmt->execute([':username' => $username]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $ex) {
            error_log($ex->getMessage());
            $errors[] = 'Something went wrong. Please try again.';
        }

        if (!$errors) {
            $active = $row && in_array($row['is_active'], [true, 't', '1', 1], true);

            if ($row && $active && password_verify($password, $row['password_hash'])) {
                login_user($row);
                set_flash('success', 'Login successful. Welcome, ' . $row['full_name'] . '!');
                redirect('dashboard.php');
            }
            // Same message for wrong username, wrong password or inactive account
            $errors[] = 'Invalid username or password.';
        }
    }
}

$pageTitle = 'Login';
require __DIR__ . '/includes/header.php';
?>
<div class="card shadow-sm auth-card">
    <div class="card-body p-4">
        <h1 class="h4 mb-1">Log in</h1>
        <p class="text-muted mb-4"><?= e(APP_NAME) ?></p>

        <?php foreach ($errors as $error): ?>
            <div class="alert alert-danger py-2"><?= e($error) ?></div>
        <?php endforeach; ?>

        <form method="post" action="" autocomplete="on">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" class="form-control" id="username" name="username"
                       value="<?= e($username) ?>" maxlength="50" required autofocus>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-success w-100">Log in</button>
        </form>

        <p class="text-center mt-3 mb-0">
            No account yet? <a href="<?= BASE_URL ?>register.php">Sign up</a>
        </p>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
