<?php
require_once __DIR__ . '/../config/config.php';

// ---------------------------------------------------------------
// Output, redirects, flash messages, CSRF (from step 4)
// ---------------------------------------------------------------

// Escape text before printing it in HTML (prevents XSS)
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// Redirect to a path inside the app, e.g. redirect('dashboard.php')
function redirect(string $path): void
{
    header('Location: ' . BASE_URL . ltrim($path, '/'));
    exit;
}

// One-time messages shown after a redirect (success, danger, warning, info)
function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

// CSRF protection for every POST form
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): bool
{
    $sent = $_POST['csrf_token'] ?? '';
    return is_string($sent) && hash_equals($_SESSION['csrf_token'] ?? '', $sent);
}

// ---------------------------------------------------------------
// Helpers for list pages (step 5)
// ---------------------------------------------------------------

// Read an integer from the query string (?page=2). Returns $default if missing or invalid.
function get_int(string $key, int $default = 0): int
{
    $value = filter_var($_GET[$key] ?? null, FILTER_VALIDATE_INT);
    return $value === false || $value === null ? $default : $value;
}

// Make a search word safe for use inside LIKE / ILIKE
function like_escape(string $text): string
{
    return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $text);
}

// Rebuild the current query string, changing only some values.
// query_string(['page' => 3]) keeps the search, filters and sorting.
function query_string(array $overrides = []): string
{
    $params = array_merge($_GET, $overrides);
    $params = array_filter($params, fn($value) => $value !== '' && $value !== null);
    return '?' . http_build_query($params);
}

// Work out page numbers for a list
function paginate(int $total, int $perPage, int $requestedPage): array
{
    $pages = max(1, (int) ceil($total / $perPage));
    $page  = min(max(1, $requestedPage), $pages);

    return [
        'total'   => $total,
        'perPage' => $perPage,
        'pages'   => $pages,
        'page'    => $page,
        'offset'  => ($page - 1) * $perPage,
    ];
}

// Bootstrap pagination buttons (Previous, 1 2 3, Next)
function render_pagination(array $pager): string
{
    if ($pager['pages'] <= 1) {
        return '';
    }

    $item = function (int $page, string $label, bool $disabled = false, bool $active = false): string {
        $class = 'page-item' . ($disabled ? ' disabled' : '') . ($active ? ' active' : '');
        $href  = $disabled ? '#' : e(query_string(['page' => $page]));
        return '<li class="' . $class . '"><a class="page-link" href="' . $href . '">' . $label . '</a></li>';
    };

    $html  = '<nav aria-label="Page navigation"><ul class="pagination justify-content-center mb-0">';
    $html .= $item($pager['page'] - 1, 'Previous', $pager['page'] <= 1);

    $start = max(1, $pager['page'] - 2);
    $end   = min($pager['pages'], $pager['page'] + 2);
    for ($i = $start; $i <= $end; $i++) {
        $html .= $item($i, (string) $i, false, $i === $pager['page']);
    }

    $html .= $item($pager['page'] + 1, 'Next', $pager['page'] >= $pager['pages']);
    return $html . '</ul></nav>';
}

// A clickable column title that sorts the list (click again to reverse)
function sort_link(string $label, string $column, string $currentSort, string $currentDir): string
{
    $isCurrent = $column === $currentSort;
    $nextDir   = ($isCurrent && $currentDir === 'asc') ? 'desc' : 'asc';
    $arrow     = $isCurrent ? ($currentDir === 'asc' ? ' &#9650;' : ' &#9660;') : '';
    $href      = e(query_string(['sort' => $column, 'dir' => $nextDir, 'page' => 1]));

    return '<a href="' . $href . '" class="text-decoration-none text-dark">' . e($label) . $arrow . '</a>';
}

// Load a lookup table for a dropdown: returns [id => name]
// Only fixed names written in our own code are passed in here, never user input.
function fetch_lookup(PDO $pdo, string $table, string $idColumn, string $nameColumn, bool $orderById = false): array
{
    foreach ([$table, $idColumn, $nameColumn] as $identifier) {
        if (!preg_match('/^[a-z_]+$/', $identifier)) {
            throw new InvalidArgumentException('Invalid identifier.');
        }
    }

    $orderBy = $orderById ? $idColumn : $nameColumn;
    $rows    = $pdo->query("SELECT $idColumn, $nameColumn FROM $table ORDER BY $orderBy")
                   ->fetchAll(PDO::FETCH_NUM);

    $lookup = [];
    foreach ($rows as [$id, $name]) {
        $lookup[(int) $id] = $name;
    }
    return $lookup;
}

// Print a value safely, or a grey "Not set" when it is empty
function display_value($value): string
{
    if ($value === null || $value === '') {
        return '<span class="text-muted">Not set</span>';
    }
    return e($value);
}

// Show a database timestamp in Philippine time
function format_datetime(?string $value): string
{
    if ($value === null || $value === '') {
        return '';
    }
    $date = new DateTime($value);
    $date->setTimezone(new DateTimeZone('Asia/Manila'));
    return $date->format('M j, Y g:i A');
}