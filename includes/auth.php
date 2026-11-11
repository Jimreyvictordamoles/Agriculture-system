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
