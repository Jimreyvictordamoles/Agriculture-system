<?php
// Helpers for the Users module (Administrators only).
require_once __DIR__ . '/../../includes/auth.php';

// Roles as [role_id => role_name]
function user_roles(PDO $pdo): array
{
    return fetch_lookup($pdo, 'user_role', 'role_id', 'role_name', true);
}

function db_true($value): bool
{
    return in_array($value, [true, 't', '1', 1, 'true'], true);
}

// An account is always in one of three states:
//   active       - can log in
//   pending      - signed up, not approved yet
//   deactivated  - was approved before, then switched off by an Administrator
function user_status(array $row): string
{
    if (db_true($row['is_active'])) {
        return 'active';
    }
    return empty($row['approved_at']) ? 'pending' : 'deactivated';
}

// "Jimrey Victor Damole" -> "JD" (falls back to the username)
function user_initials(string $fullName, string $username): string
{
    $parts = preg_split('/\s+/', trim($fullName), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if (!$parts) {
        return mb_strtoupper(mb_substr($username, 0, 1));
    }
    $initials = mb_substr($parts[0], 0, 1);
    if (count($parts) > 1) {
        $initials .= mb_substr(end($parts), 0, 1);
    }
    return mb_strtoupper($initials);
}

// Keep only the list settings (search, filters, sorting, page) from a query string,
// so an action button can bring the Administrator back to the same view.
function users_back_query(string $raw): string
{
    parse_str($raw, $parsed);
    $keep = [];
    foreach (['q', 'role_id', 'status', 'sort', 'dir', 'page'] as $key) {
        if (isset($parsed[$key]) && is_string($parsed[$key]) && $parsed[$key] !== '') {
            $keep[$key] = $parsed[$key];
        }
    }
    return $keep ? '?' . http_build_query($keep) : '';
}

// How many other active Administrators exist (not counting $excludeUserId)
function other_active_admins(PDO $pdo, int $excludeUserId): int
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM users u JOIN user_role r ON r.role_id = u.role_id
          WHERE r.role_name = 'Administrator' AND u.is_active = TRUE AND u.user_id <> :id"
    );
    $stmt->execute([':id' => $excludeUserId]);
    return (int) $stmt->fetchColumn();
}

/**
 * Change an account's role, status or name. Every change goes through here so the
 * safeguards cannot be skipped. Returns an error message, or null when it worked.
 *   - an Administrator cannot demote or deactivate their own account
 *   - the last active Administrator can never be demoted or deactivated
 * The first time an account becomes active, approved_at is stamped; that is what
 * later tells "deactivated" apart from "still waiting for approval".
 */
function change_user(PDO $pdo, int $targetId, int $actorId, ?int $newRoleId, ?bool $newActive, ?string $newFullName = null): ?string
{
    try {
        $pdo->beginTransaction();

        // Lock every active Administrator row so two Administrators cannot remove each other at once
        $pdo->query(
            "SELECT u.user_id FROM users u JOIN user_role r ON r.role_id = u.role_id
              WHERE r.role_name = 'Administrator' AND u.is_active = TRUE FOR UPDATE OF u"
        );

        $stmt = $pdo->prepare(
            'SELECT u.user_id, u.full_name, u.is_active, u.role_id, r.role_name
               FROM users u JOIN user_role r ON r.role_id = u.role_id
              WHERE u.user_id = :id FOR UPDATE OF u'
        );
        $stmt->execute([':id' => $targetId]);
        $target = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$target) {
            $pdo->rollBack();
            return 'User not found.';
        }

        $roles = user_roles($pdo);
        $role  = $newRoleId ?? (int) $target['role_id'];
        if (!isset($roles[$role])) {
            $pdo->rollBack();
            return 'That role does not exist.';
        }

        $wasActive   = db_true($target['is_active']);
        $active      = $newActive ?? $wasActive;
        $wasAdmin    = $target['role_name'] === 'Administrator';
        $willBeAdmin = $roles[$role] === 'Administrator';

        if ($targetId === $actorId) {
            if ($wasAdmin && !$willBeAdmin) {
                $pdo->rollBack();
                return 'You cannot remove the Administrator role from your own account.';
            }
            if ($wasActive && !$active) {
                $pdo->rollBack();
                return 'You cannot deactivate your own account.';
            }
        }

        $losesAdminAccess = $wasAdmin && $wasActive && (!$willBeAdmin || !$active);
        if ($losesAdminAccess && other_active_admins($pdo, $targetId) === 0) {
            $pdo->rollBack();
            return 'This is the last active Administrator, so it cannot be demoted or deactivated.';
        }

        $name = $newFullName !== null ? $newFullName : $target['full_name'];
        $stmt = $pdo->prepare(
            'UPDATE users
                SET role_id = :role,
                    is_active = CAST(:active AS BOOLEAN),
                    full_name = :name,
                    approved_at = CASE WHEN CAST(:approve AS BOOLEAN) THEN COALESCE(approved_at, NOW()) ELSE approved_at END
              WHERE user_id = :id'
        );
        $stmt->execute([
            ':role'    => $role,
            ':active'  => $active ? 't' : 'f',
            ':approve' => $active ? 't' : 'f',
            ':name'    => $name,
            ':id'      => $targetId,
        ]);
        $pdo->commit();
        return null;
    } catch (PDOException $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log($ex->getMessage());
        return 'Could not save the change. Please try again.';
    }
}

/**
 * Permanently delete an account. Returns an error message, or null when it worked.
 *   - you cannot remove your own account
 *   - an active account must be deactivated first (so a working login is never
 *     deleted by one accidental click)
 */
function remove_user(PDO $pdo, int $targetId, int $actorId): ?string
{
    if ($targetId === $actorId) {
        return 'You cannot remove your own account.';
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('SELECT is_active FROM users WHERE user_id = :id FOR UPDATE');
        $stmt->execute([':id' => $targetId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $pdo->rollBack();
            return 'User not found.';
        }
        if (db_true($row['is_active'])) {
            $pdo->rollBack();
            return 'This account is active. Deactivate it first, then remove it.';
        }

        $stmt = $pdo->prepare('DELETE FROM users WHERE user_id = :id');
        $stmt->execute([':id' => $targetId]);
        $pdo->commit();
        return null;
    } catch (PDOException $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log($ex->getMessage());
        if ($ex->getCode() === '23503') {   // foreign key violation (safety net)
            return 'This account is linked to other records and cannot be removed. Keep it deactivated instead.';
        }
        return 'Could not remove the account. Please try again.';
    }
}

// Validate the "add user" form. Returns ['errors' => [], 'values' => [], 'clean' => []]
function validate_new_user(array $input, array $roles): array
{
    $values = [
        'full_name' => trim((string) ($input['full_name'] ?? '')),
        'username'  => trim((string) ($input['username'] ?? '')),
        'role_id'   => trim((string) ($input['role_id'] ?? '')),
        'is_active' => isset($input['is_active']) ? '1' : '',
    ];
    $password = (string) ($input['password'] ?? '');
    $confirm  = (string) ($input['confirm_password'] ?? '');
    $errors   = [];

    if ($values['full_name'] === '' || mb_strlen($values['full_name']) > 100) {
        $errors[] = 'Full name is required (maximum 100 characters).';
    }
    if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $values['username'])) {
        $errors[] = 'Username must be 3 to 50 characters: letters, numbers, dot, dash or underscore.';
    }
    $roleId = filter_var($values['role_id'], FILTER_VALIDATE_INT);
    if ($roleId === false || !isset($roles[$roleId])) {
        $errors[] = 'Please choose a role.';
    }
    if (strlen($password) < 8 || strlen($password) > 72) {
        $errors[] = 'Password must be between 8 and 72 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'The passwords do not match.';
    }

    return [
        'errors' => $errors,
        'values' => $values,
        'clean'  => [
            'full_name' => $values['full_name'],
            'username'  => $values['username'],
            'role_id'   => (int) $roleId,
            'is_active' => $values['is_active'] === '1',
            'password'  => $password,
        ],
    ];
}
