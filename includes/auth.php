<?php
require_once __DIR__ . '/functions.php';

function is_logged_in(): bool
{
    return isset($_SESSION['user']);
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function login_user(array $row): void
{
    session_regenerate_id(true);   // prevents session fixation
    $_SESSION['user'] = [
        'user_id'   => (int) $row['user_id'],
        'username'  => $row['username'],
        'full_name' => $row['full_name'],
        'role_id'   => (int) $row['role_id'],
        'role_name' => $row['role_name'],
    ];
}

function logout_user(): void
{
    $_SESSION = [];
    session_regenerate_id(true);
}

// Put this at the top of every page that needs a logged-in user
function require_login(): void
{
    if (!is_logged_in()) {
        set_flash('warning', 'Please log in to continue.');
        redirect('login.php');
    }
    refresh_session_user();
}

// Re-read the account on every request, so an Administrator's change (deactivating
// an account or changing a role) takes effect immediately and not at the next login.
function refresh_session_user(): void
{
    global $pdo;
    $id = (int) ($_SESSION['user']['user_id'] ?? 0);

    try {
        $stmt = $pdo->prepare(
            'SELECT u.full_name, u.role_id, u.is_active, r.role_name
             FROM users u JOIN user_role r ON r.role_id = u.role_id
             WHERE u.user_id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $ex) {
        error_log($ex->getMessage());
        return;   // keep the current session if the database has a hiccup
    }

    $active = $row && in_array($row['is_active'], [true, 't', '1', 1], true);
    if (!$active) {
        logout_user();
        set_flash('warning', 'Your account is no longer active. Please contact an Administrator.');
        redirect('login.php');
    }

    $_SESSION['user']['full_name'] = $row['full_name'];
    $_SESSION['user']['role_id']   = (int) $row['role_id'];
    $_SESSION['user']['role_name'] = $row['role_name'];
}

function is_admin(): bool
{
    return (current_user()['role_name'] ?? '') === 'Administrator';
}

// Put this at the top of pages only Administrators may open
function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        set_flash('danger', 'You do not have permission to open that page.');
        redirect('dashboard.php');
    }
}
