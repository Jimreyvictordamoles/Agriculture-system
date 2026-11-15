<?php
require_once __DIR__ . '/../../includes/auth.php';
require_admin();   // only Administrators may manage users
require_once __DIR__ . '/_helpers.php';

$perPage = 10;
$q       = trim($_GET['q'] ?? '');
$roleId  = get_int('role_id', 0);
$status  = $_GET['status'] ?? '';
if (!in_array($status, ['active', 'pending', 'deactivated'], true)) {
    $status = '';
}

// Only these columns may be used for sorting (never trust ?sort= directly)
$sortMap = [
    'id'       => ['u.user_id'],
    'name'     => ['u.full_name'],
    'username' => ['u.username'],
    'role'     => ['r.role_name'],
    'status'   => ['u.is_active', 'u.approved_at'],
    'created'  => ['u.created_at'],
];
$sort = $_GET['sort'] ?? 'created';
if (!isset($sortMap[$sort])) {
    $sort = 'created';
}
$dir      = (($_GET['dir'] ?? 'desc') === 'asc') ? 'asc' : 'desc';
$orderSql = implode(', ', array_map(fn($column) => "$column $dir NULLS LAST", $sortMap[$sort]));

$where  = [];
$params = [];
if ($q !== '') {
    $where[]       = '(u.username ILIKE :q1 OR u.full_name ILIKE :q2)';
    $params[':q1'] = '%' . like_escape($q) . '%';
    $params[':q2'] = '%' . like_escape($q) . '%';
}
if ($roleId > 0) {
    $where[]            = 'u.role_id = :role_id';
    $params[':role_id'] = $roleId;
}
if ($status === 'active') {
    $where[] = 'u.is_active = TRUE';
} elseif ($status === 'pending') {
    $where[] = 'u.is_active = FALSE AND u.approved_at IS NULL';
} elseif ($status === 'deactivated') {
    $where[] = 'u.is_active = FALSE AND u.approved_at IS NOT NULL';
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$roles  = [];
$users  = [];
$counts = ['total' => 0, 'active' => 0, 'pending' => 0, 'deactivated' => 0];
$pager  = paginate(0, $perPage, 1);

try {
    $roles = user_roles($pdo);

    // Numbers shown on the status tabs (always for all users, whatever the search)
    $counts = $pdo->query(
        'SELECT COUNT(*) AS total,
                COUNT(*) FILTER (WHERE is_active) AS active,
                COUNT(*) FILTER (WHERE NOT is_active AND approved_at IS NULL) AS pending,
                COUNT(*) FILTER (WHERE NOT is_active AND approved_at IS NOT NULL) AS deactivated
           FROM users'
    )->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users u JOIN user_role r ON r.role_id = u.role_id $whereSql");
    $stmt->execute($params);
    $pager = paginate((int) $stmt->fetchColumn(), $perPage, get_int('page', 1));

    $stmt = $pdo->prepare(
        "SELECT u.user_id, u.username, u.full_name, u.is_active, u.approved_at, u.created_at, r.role_name
           FROM users u JOIN user_role r ON r.role_id = u.role_id
           $whereSql
          ORDER BY $orderSql, u.user_id ASC
          LIMIT :limit OFFSET :offset"
    );
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $pager['offset'], PDO::PARAM_INT);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $ex) {
    error_log($ex->getMessage());
    set_flash('danger', 'Could not load the users. Please try again.');
}

$myId      = (int) current_user()['user_id'];
$back      = $_SERVER['QUERY_STRING'] ?? '';
$pageTitle = 'Users';
require __DIR__ . '/../../includes/header.php';

$from = $pager['total'] === 0 ? 0 : $pager['offset'] + 1;
$to   = min($pager['offset'] + $perPage, $pager['total']);

$tabs = [
    ''            => ['All',         (int) $counts['total']],
    'active'      => ['Active',      (int) $counts['active']],
    'pending'     => ['Pending',     (int) $counts['pending']],
    'deactivated' => ['Deactivated', (int) $counts['deactivated']],
];
$emptyText = [
    ''            => 'No users found.',
    'active'      => 'No active users match your search.',
    'pending'     => 'No accounts are waiting for approval.',
    'deactivated' => 'No deactivated accounts.',
];
?>
<style>
    .u-avatar { width: 2.25rem; height: 2.25rem; border-radius: 50%; flex-shrink: 0;
                display: inline-flex; align-items: center; justify-content: center;
                font-size: .8rem; font-weight: 600;
                background: var(--bs-success-bg-subtle); color: var(--bs-success-text-emphasis); }
    .u-avatar.is-off { background: var(--bs-secondary-bg); color: var(--bs-secondary-color); }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h3 mb-0">Users</h1>
        <div class="text-muted small">Approve sign-ups, manage roles and control who can log in.</div>
    </div>
    <a href="add.php" class="btn btn-success">Add user</a>
</div>

<ul class="nav nav-pills gap-1 mb-3">
    <?php foreach ($tabs as $key => [$label, $count]): $isCurrent = $status === $key; ?>
        <li class="nav-item">
            <a class="nav-link py-1 px-3 <?= $isCurrent ? 'active bg-success' : 'text-body' ?>"
               href="<?= e(query_string(['status' => $key, 'page' => 1])) ?>">
                <?= e($label) ?>
                <span class="badge rounded-pill ms-1 <?= $isCurrent ? 'text-bg-light' : ($key === 'pending' && $count > 0 ? 'text-bg-warning' : 'text-bg-secondary') ?>"><?= $count ?></span>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<form method="get" action="" class="card card-body mb-3">
    <input type="hidden" name="status" value="<?= e($status) ?>">
    <input type="hidden" name="sort" value="<?= e($sort) ?>">
    <input type="hidden" name="dir" value="<?= e($dir) ?>">
    <div class="row g-2 align-items-end">
        <div class="col-md-6">
            <label for="q" class="form-label mb-1">Search</label>
            <input type="text" class="form-control" id="q" name="q" value="<?= e($q) ?>" placeholder="Username or full name">
        </div>
        <div class="col-md-3">
            <label for="role_id" class="form-label mb-1">Role</label>
            <select class="form-select" id="role_id" name="role_id">
                <option value="">All roles</option>
                <?php foreach ($roles as $rid => $name): ?>
                    <option value="<?= (int) $rid ?>" <?= $roleId === (int) $rid ? 'selected' : '' ?>><?= e($name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-success flex-fill">Filter</button>
            <a href="<?= e($status !== '' ? '?status=' . $status : 'index.php') ?>" class="btn btn-outline-secondary">Reset</a>
        </div>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th><?= sort_link('ID', 'id', $sort, $dir) ?></th>
                    <th><?= sort_link('User', 'name', $sort, $dir) ?></th>
                    <th><?= sort_link('Role', 'role', $sort, $dir) ?></th>
                    <th><?= sort_link('Status', 'status', $sort, $dir) ?></th>
                    <th><?= sort_link('Created', 'created', $sort, $dir) ?></th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$users): ?>
                <tr><td colspan="6" class="text-center text-muted py-4"><?= e($q !== '' || $roleId > 0 ? 'No users match your search.' : $emptyText[$status]) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($users as $u):
                $uid     = (int) $u['user_id'];
                $state   = user_status($u);
                $isAdmin = $u['role_name'] === 'Administrator';
                $isSelf  = $uid === $myId;
                $fullName = $u['full_name'];
            ?>
                <tr>
                    <td class="text-muted"><?= $uid ?></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <span class="u-avatar <?= $state === 'active' ? '' : 'is-off' ?>" aria-hidden="true"><?= e(user_initials($fullName, $u['username'])) ?></span>
                            <div class="lh-sm">
                                <div class="fw-semibold">
                                    <?= e($fullName) ?>
                                    <?php if ($isSelf): ?><span class="badge text-bg-secondary fw-normal ms-1">You</span><?php endif; ?>
                                </div>
                                <div class="text-muted small">@<?= e($u['username']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="badge border <?= $isAdmin ? 'bg-success-subtle text-success-emphasis border-success-subtle' : 'bg-light text-secondary' ?>">
                            <?= e($u['role_name']) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($state === 'active'): ?>
                            <span class="badge rounded-pill text-bg-success">Active</span>
                        <?php elseif ($state === 'pending'): ?>
                            <span class="badge rounded-pill text-bg-warning" title="Signed up and waiting for approval">Pending approval</span>
                        <?php else: ?>
                            <span class="badge rounded-pill text-bg-secondary" title="Was approved before; an Administrator switched it off">Deactivated</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-nowrap"><?= e(format_datetime($u['created_at'])) ?></td>
                    <td class="text-end text-nowrap">
                        <a href="edit.php?id=<?= $uid ?>" class="btn btn-sm btn-outline-secondary">Edit</a>

                        <?php if ($state === 'pending' || $state === 'deactivated'): ?>
                            <form method="post" action="action.php" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="user_id" value="<?= $uid ?>">
                                <input type="hidden" name="do" value="activate">
                                <input type="hidden" name="back" value="<?= e($back) ?>">
                                <button type="submit" class="btn btn-sm btn-success"><?= $state === 'pending' ? 'Approve' : 'Reactivate' ?></button>
                            </form>
                            <form method="post" action="action.php" class="d-inline"
                                  onsubmit="return confirm('<?= $state === 'pending'
                                      ? 'Reject the sign-up request from ' . e(addslashes($fullName)) . '? The account will be deleted.'
                                      : 'Permanently remove ' . e(addslashes($fullName)) . ' (@' . e(addslashes($u['username'])) . ')? This cannot be undone.' ?>');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="user_id" value="<?= $uid ?>">
                                <input type="hidden" name="do" value="remove">
                                <input type="hidden" name="back" value="<?= e($back) ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"><?= $state === 'pending' ? 'Reject' : 'Remove' ?></button>
                            </form>
                        <?php elseif (!$isSelf): ?>
                            <form method="post" action="action.php" class="d-inline"
                                  onsubmit="return confirm('Deactivate <?= e(addslashes($fullName)) ?>? They will no longer be able to log in. You can reactivate or remove the account later.');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="user_id" value="<?= $uid ?>">
                                <input type="hidden" name="do" value="deactivate">
                                <input type="hidden" name="back" value="<?= e($back) ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">Deactivate</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-2 mt-3">
    <div class="text-muted small">
        Showing <?= $from ?>&ndash;<?= $to ?> of <?= $pager['total'] ?> user<?= $pager['total'] === 1 ? '' : 's' ?>
    </div>
    <?= render_pagination($pager) ?>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>