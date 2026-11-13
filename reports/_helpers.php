<?php
require_once __DIR__ . '/../includes/functions.php';

// ---------------------------------------------------------------
// Date helpers (shared with the other modules; skipped if they already exist)
// ---------------------------------------------------------------
if (!function_exists('today_manila')) {
    function today_manila(): string
    {
        return (new DateTime('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d');
    }
}

if (!function_exists('valid_date_string')) {
    function valid_date_string(string $value): bool
    {
        $date = DateTime::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }
}

if (!function_exists('format_date')) {
    function format_date(?string $value): string
    {
        if ($value === null || !valid_date_string($value)) {
            return '';
        }
        return DateTime::createFromFormat('!Y-m-d', $value)->format('M j, Y');
    }
}

if (!function_exists('status_badge')) {
    function status_badge(string $name): string
    {
        $classes = [
            'Planted'           => 'bg-secondary',
            'Growing'           => 'bg-primary',
            'Ready for Harvest' => 'bg-warning text-dark',
            'Harvested'         => 'bg-success',
            'Failed'            => 'bg-danger',
        ];
        $class = $classes[$name] ?? 'bg-secondary';
        return '<span class="badge ' . $class . '">' . e($name) . '</span>';
    }
}

// ---------------------------------------------------------------
// Date range filter
// ---------------------------------------------------------------

// Reads ?date_from and ?date_to. Returns [from, to, errorMessage].
function report_dates(): array
{
    $from = trim($_GET['date_from'] ?? '');
    $to   = trim($_GET['date_to'] ?? '');
    if (!valid_date_string($from)) {
        $from = '';
    }
    if (!valid_date_string($to)) {
        $to = '';
    }

    $error = '';
    if ($from !== '' && $to !== '' && $from > $to) {
        $error = 'The "from" date cannot be later than the "to" date.';
    }
    return [$from, $to, $error];
}

// Adds "column >= from" and "column <= to" to a query (uses :date_from and :date_to)
function add_date_range(string $column, string $from, string $to, array &$where, array &$params): void
{
    if ($from !== '') {
        $where[]              = "$column >= :date_from";
        $params[':date_from'] = $from;
    }
    if ($to !== '') {
        $where[]            = "$column <= :date_to";
        $params[':date_to'] = $to;
    }
}

// "Oct 1, 2026 to Nov 13, 2026", "From Oct 1, 2026", "Up to Nov 13, 2026" or "All dates"
function report_range_label(string $from, string $to): string
{
    if ($from !== '' && $to !== '') {
        return format_date($from) . ' to ' . format_date($to);
    }
    if ($from !== '') {
        return 'From ' . format_date($from);
    }
    if ($to !== '') {
        return 'Up to ' . format_date($to);
    }
    return 'All dates';
}

// ---------------------------------------------------------------
// Dropdown lists for the filters
// ---------------------------------------------------------------

// [farm_id => "Farm (Last, First)"]
function report_farm_options(PDO $pdo): array
{
    $rows = $pdo->query(
        "SELECT fm.farm_id, fm.farm_name || ' (' || fr.last_name || ', ' || fr.first_name || ')'
         FROM farm fm JOIN farmer fr ON fr.farmer_id = fm.farmer_id
         ORDER BY fm.farm_name, fr.last_name, fr.first_name"
    )->fetchAll(PDO::FETCH_NUM);

    $options = [];
    foreach ($rows as [$id, $label]) {
        $options[(int) $id] = $label;
    }
    return $options;
}

// [crop_id => "Rice (Jasmine)"]
function report_crop_options(PDO $pdo): array
{
    $rows = $pdo->query(
        "SELECT crop_id, crop_name || COALESCE(' (' || variety || ')', '')
         FROM crop ORDER BY crop_name, variety"
    )->fetchAll(PDO::FETCH_NUM);

    $options = [];
    foreach ($rows as [$id, $label]) {
        $options[(int) $id] = $label;
    }
    return $options;
}

// ---------------------------------------------------------------
// Harvest summary used by the "total quantity" and "estimated value" reports
// ---------------------------------------------------------------

// What the summary can be grouped by
function harvest_group_options(): array
{
    return ['crop' => 'Crop', 'farm' => 'Farm', 'month' => 'Month'];
}

// Returns one row per group: label, harvest_count, total_kg, total_value
function harvest_summary(PDO $pdo, string $groupBy, string $from, string $to, int $farmId, int $cropId): array
{
    // Only these expressions may be used (never trust ?group_by= directly)
    $labelSql = [
        'crop'  => "c.crop_name || COALESCE(' (' || c.variety || ')', '')",
        'farm'  => "fm.farm_name || ' (' || fr.last_name || ', ' || fr.first_name || ')'",
        'month' => "to_char(h.harvest_date, 'YYYY-MM')",
    ];
    if (!isset($labelSql[$groupBy])) {
        $groupBy = 'crop';
    }

    $where  = [];
    $params = [];
    add_date_range('h.harvest_date', $from, $to, $where, $params);
    if ($farmId > 0) {
        $where[]            = 'p.farm_id = :farm_id';
        $params[':farm_id'] = $farmId;
    }
    if ($cropId > 0) {
        $where[]            = 'p.crop_id = :crop_id';
        $params[':crop_id'] = $cropId;
    }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $orderSql = $groupBy === 'month' ? 'label ASC' : 'total_kg DESC, label ASC';

    $sql = "SELECT {$labelSql[$groupBy]} AS label,
                   COUNT(*) AS harvest_count,
                   SUM(h.quantity_kg) AS total_kg,
                   COALESCE(SUM(h.quantity_kg * h.selling_price_per_kg), 0) AS total_value
            FROM harvest h
            JOIN planting p ON p.planting_id = h.planting_id
            JOIN farm fm ON fm.farm_id = p.farm_id
            JOIN farmer fr ON fr.farmer_id = fm.farmer_id
            JOIN crop c ON c.crop_id = p.crop_id
            $whereSql
            GROUP BY label
            ORDER BY $orderSql";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // "2026-10" becomes "Oct 2026"
    if ($groupBy === 'month') {
        foreach ($rows as &$row) {
            $date = DateTime::createFromFormat('!Y-m', $row['label']);
            if ($date) {
                $row['label'] = $date->format('M Y');
            }
        }
        unset($row);
    }
    return $rows;
}

// ---------------------------------------------------------------
// Output: CSV download, toolbar and print styles
// ---------------------------------------------------------------

// Stops spreadsheet programs from running text that starts with = + - @ as a formula
function csv_safe($value)
{
    if (is_string($value) && $value !== '' && strpos('=+-@', $value[0]) !== false) {
        return "'" . $value;
    }
    return $value;
}

// Sends a CSV file to the browser and stops the page
function csv_download(string $filename, array $headers, array $rows): void
{
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");   // lets Excel read the file as UTF-8
    fputcsv($out, $headers, ',', '"', '\\');
    foreach ($rows as $row) {
        fputcsv($out, array_map('csv_safe', $row), ',', '"', '\\');
    }
    fclose($out);
    exit;
}

function wants_csv(): bool
{
    return ($_GET['export'] ?? '') === 'csv';
}

// Print and Export buttons
function report_toolbar(bool $hasRows): string
{
    $html  = '<div class="d-flex gap-2 no-print">';
    $html .= '<button type="button" class="btn btn-outline-secondary" onclick="window.print()">Print</button>';
    if ($hasRows) {
        $html .= '<a href="' . e(query_string(['export' => 'csv'])) . '" class="btn btn-outline-success">Export CSV</a>';
    }
    return $html . '</div>';
}

// Hides the menu and the filter form when the report is printed
function report_print_styles(): string
{
    return '<style>@media print { .navbar, footer, .no-print, .alert-dismissible { display: none !important; }'
         . ' body { background: #fff; } .card { border: 0; box-shadow: none !important; } }</style>';
}
