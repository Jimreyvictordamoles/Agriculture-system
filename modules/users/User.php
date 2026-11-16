<?php
require_once __DIR__ . '/../../includes/functions.php';

// Wrapped in function_exists() so this file can sit next to the old _helpers.php
// until every Users page has been moved over.

if (!function_exists('db_true')) {
    function db_true($value): bool
    {
        return in_array($value, [true, 't', '1', 1, 'true'], true);
    }
}

// An account is always in one of three states:
//   active       - can log in
//   pending      - signed up, not approved yet
//   deactivated  - was approved before, then switched off by an Administrator
if (!function_exists('user_status')) {
    function user_status(array $row): string
    {
        if (db_true($row['is_active'])) {
            return 'active';
        }
        return empty($row['approved_at']) ? 'pending' : 'deactivated';
    }
}

// "Jimrey Victor Damole" -> "JD" (falls back to the username)
if (!function_exists('user_initials')) {
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
}

// Keep only the list settings (search, filters, sorting, page) from a query string,
// so an action button can bring the Administrator back to the same view.
if (!function_exists('users_back_query')) {
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
}

class User {
    private PDO $pdo;

    // Only these columns may be used for sorting (never trust ?sort= directly)
    private const SORT_MAP = [
        'id'       => ['u.user_id'],
        'name'     => ['u.full_name'],
        'username' => ['u.username'],
        'role'     => ['r.role_name'],
        'status'   => ['u.is_active', 'u.approved_at'],
        'created'  => ['u.created_at'],
    ];

    private const FROM_SQL = 'FROM users u JOIN user_role r ON r.role_id = u.role_id';

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function isValidSort(string $sort): bool {
        return isset(self::SORT_MAP[$sort]);
    }

    // [role_id => role_name]
    public function fetchRoles(): array {
        return fetch_lookup($this->pdo, 'user_role', 'role_id', 'role_name', true);
    }

    // Numbers for the status tabs (always for all users, whatever the search)
    public function statusCounts(): array {
        return $this->pdo->query(
            'SELECT COUNT(*) AS total,
                    COUNT(*) FILTER (WHERE is_active) AS active,
                    COUNT(*) FILTER (WHERE NOT is_active AND approved_at IS NULL) AS pending,
                    COUNT(*) FILTER (WHERE NOT is_active AND approved_at IS NOT NULL) AS deactivated
               FROM users'
        )->fetch(PDO::FETCH_ASSOC);
    }

    // $filters: q, role_id, status ('active' | 'pending' | 'deactivated' | '')
    private function buildWhere(array $filters): array {
        $where  = [];
        $params = [];

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[]       = '(u.username ILIKE :q1 OR u.full_name ILIKE :q2)';
            $params[':q1'] = '%' . like_escape($q) . '%';
            $params[':q2'] = '%' . like_escape($q) . '%';
        }
        if (($filters['role_id'] ?? 0) > 0) {
            $where[]            = 'u.role_id = :role_id';
            $params[':role_id'] = (int) $filters['role_id'];
        }
        $status = $filters['status'] ?? '';
        if ($status === 'active') {
            $where[] = 'u.is_active = TRUE';
        } elseif ($status === 'pending') {
            $where[] = 'u.is_active = FALSE AND u.approved_at IS NULL';
        } elseif ($status === 'deactivated') {
            $where[] = 'u.is_active = FALSE AND u.approved_at IS NOT NULL';
        }

        return [$where ? 'WHERE ' . implode(' AND ', $where) : '', $params];
    }

    // Number of users matching the filters
    public function count(array $filters): int {
        [$whereSql, $params] = $this->buildWhere($filters);

        $stmt = $this->pdo->prepare('SELECT COUNT(*) ' . self::FROM_SQL . " $whereSql");
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function getAll(array $filters, string $sort, string $dir, int $limit, int $offset): array {
        [$whereSql, $params] = $this->buildWhere($filters);

        if (!$this->isValidSort($sort)) {
            $sort = 'created';
        }
        $dir      = strtolower($dir) === 'asc' ? 'asc' : 'desc';
        $orderSql = implode(', ', array_map(
            fn($column) => "$column $dir NULLS LAST",
            self::SORT_MAP[$sort]
        ));

        $sql = 'SELECT u.user_id, u.username, u.full_name, u.is_active, u.approved_at, u.created_at, r.role_name '
             . self::FROM_SQL . "
                $whereSql
                ORDER BY $orderSql, u.user_id ASC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // How many other active Administrators exist (not counting $excludeUserId)
    public function otherActiveAdmins(int $excludeUserId): int {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM users u JOIN user_role r ON r.role_id = u.role_id
              WHERE r.role_name = 'Administrator' AND u.is_active = TRUE AND u.user_id <> :id"
        );
        $stmt->execute([':id' => $excludeUserId]);

        return (int) $stmt->fetchColumn();
    }

    // One account's details, or null when it doesn't exist
    public function find(int $id): ?array {
        $stmt = $this->pdo->prepare(
            'SELECT user_id, username, full_name, role_id, is_active, approved_at, created_at
            FROM users WHERE user_id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    // Creates an account. Returns an error message, or null when it worked.
    public function create(array $c): ?string {
        try {
            $stmt = $this->pdo->prepare(
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
            return null;
        } catch (PDOException $ex) {
            if ($ex->getCode() === '23505') {
                return 'That username is already taken.';
            }
            error_log($ex->getMessage());
            return 'Something went wrong. Please try again.';
        }
    }

    /**
     * Change an account's role, status or name. Every change goes through here so the
     * safeguards cannot be skipped. Returns an error message, or null when it worked.
     *   - an Administrator cannot demote or deactivate their own account
     *   - the last active Administrator can never be demoted or deactivated
     * The first time an account becomes active, approved_at is stamped; that is what
     * later tells "deactivated" apart from "still waiting for approval".
     */
    public function change(int $targetId, int $actorId, ?int $newRoleId, ?bool $newActive, ?string $newFullName = null): ?string {
        $pdo = $this->pdo;

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

            $roles = $this->fetchRoles();
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
            if ($losesAdminAccess && $this->otherActiveAdmins($targetId) === 0) {
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
    public function remove(int $targetId, int $actorId): ?string {
        if ($targetId === $actorId) {
            return 'You cannot remove your own account.';
        }

        $pdo = $this->pdo;

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
}