<?php
require_once __DIR__ . '/../includes/functions.php';

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

// Reads ?date_from and ?date_to. Returns [from, to, errorMessage].
if (!function_exists('report_dates')) {
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
}

// Adds "column >= from" and "column <= to" to a query (uses :date_from and :date_to)
if (!function_exists('add_date_range')) {
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
}

// "Oct 1, 2026 to Nov 13, 2026", "From Oct 1, 2026", "Up to Nov 13, 2026" or "All dates"
if (!function_exists('report_range_label')) {
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
}

// Stops spreadsheet programs from running text that starts with = + - @ as a formula
if (!function_exists('csv_safe')) {
    function csv_safe($value)
    {
        if (is_string($value) && $value !== '' && strpos('=+-@', $value[0]) !== false) {
            return "'" . $value;
        }
        return $value;
    }
}

// Sends a CSV file to the browser and stops the page
if (!function_exists('csv_download')) {
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
}

if (!function_exists('wants_csv')) {
    function wants_csv(): bool
    {
        return ($_GET['export'] ?? '') === 'csv';
    }
}

// Print and Export buttons
if (!function_exists('report_toolbar')) {
    function report_toolbar(bool $hasRows): string
    {
        $html  = '<div class="d-flex gap-2 no-print">';
        $html .= '<button type="button" class="btn btn-outline-secondary" onclick="window.print()">Print</button>';
        if ($hasRows) {
            $html .= '<a href="' . e(query_string(['export' => 'csv'])) . '" class="btn btn-outline-success">Export CSV</a>';
        }
        return $html . '</div>';
    }
}

// Hides the menu and the filter form when the report is printed
if (!function_exists('report_print_styles')) {
    function report_print_styles(): string
    {
        return '<style>@media print { .navbar, footer, .no-print, .alert-dismissible { display: none !important; }'
             . ' body { background: #fff; } .card { border: 0; box-shadow: none !important; } }</style>';
    }
}

class Report {
    private PDO $pdo;

    // What the harvest summary can be grouped by
    private const GROUP_OPTIONS = ['crop' => 'Crop', 'farm' => 'Farm', 'month' => 'Month'];

    // Only these expressions may be used (never trust ?group_by= directly)
    private const LABEL_SQL = [
        'crop'  => "c.crop_name || COALESCE(' (' || c.variety || ')', '')",
        'farm'  => "fm.farm_name || ' (' || fr.last_name || ', ' || fr.first_name || ')'",
        'month' => "to_char(h.harvest_date, 'YYYY-MM')",
    ];

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function harvestGroupOptions(): array {
        return self::GROUP_OPTIONS;
    }

    // [farm_id => "Farm (Last, First)"]
    public function farmOptions(): array {
        $rows = $this->pdo->query(
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
    public function cropOptions(): array {
        $rows = $this->pdo->query(
            "SELECT crop_id, crop_name || COALESCE(' (' || variety || ')', '')
             FROM crop ORDER BY crop_name, variety"
        )->fetchAll(PDO::FETCH_NUM);

        $options = [];
        foreach ($rows as [$id, $label]) {
            $options[(int) $id] = $label;
        }
        return $options;
    }

    // One row per group: label, harvest_count, total_kg, total_value
    public function harvestSummary(string $groupBy, string $from, string $to, int $farmId, int $cropId): array {
        if (!isset(self::LABEL_SQL[$groupBy])) {
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
        $labelSql = self::LABEL_SQL[$groupBy];

        $sql = "SELECT $labelSql AS label,
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

        $stmt = $this->pdo->prepare($sql);
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

    // [status_id => status_name]
    public function statusOptions(): array {
        return fetch_lookup($this->pdo, 'planting_status', 'status_id', 'status_name', true);
    }

    // Filters that apply to every planting: [where[], params[]]
    private function plantingFilters(string $from, string $to, int $farmId, int $cropId): array {
        $where  = [];
        $params = [];
        add_date_range('p.planting_date', $from, $to, $where, $params);
        if ($farmId > 0) {
            $where[]            = 'p.farm_id = :farm_id';
            $params[':farm_id'] = $farmId;
        }
        if ($cropId > 0) {
            $where[]            = 'p.crop_id = :crop_id';
            $params[':crop_id'] = $cropId;
        }
        return [$where, $params];
    }

    // One row per status (statuses with no plantings still appear, because the filters go in the JOIN):
    // status_id, status_name, planting_count, total_area
    public function plantingStatusSummary(string $from, string $to, int $farmId, int $cropId): array {
        [$where, $params] = $this->plantingFilters($from, $to, $farmId, $cropId);
        $joinSql = $where ? 'AND ' . implode(' AND ', $where) : '';

        $stmt = $this->pdo->prepare(
            "SELECT ps.status_id, ps.status_name, COUNT(p.planting_id) AS planting_count,
                    COALESCE(SUM(p.area_planted), 0) AS total_area
             FROM planting_status ps
             LEFT JOIN planting p ON p.status_id = ps.status_id $joinSql
             GROUP BY ps.status_id, ps.status_name
             ORDER BY ps.status_id"
        );
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // The planting records, optionally limited to one status
    public function plantingRecords(int $statusId, string $from, string $to, int $farmId, int $cropId): array {
        [$where, $params] = $this->plantingFilters($from, $to, $farmId, $cropId);
        if ($statusId > 0) {
            $where[]              = 'p.status_id = :status_id';
            $params[':status_id'] = $statusId;
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmt = $this->pdo->prepare(
            "SELECT p.planting_id, p.planting_date, p.expected_harvest_date, p.area_planted,
                    c.crop_name, c.variety, fm.farm_name, fr.first_name, fr.last_name, ps.status_name
             FROM planting p
             JOIN farm fm ON fm.farm_id = p.farm_id
             JOIN farmer fr ON fr.farmer_id = fm.farmer_id
             JOIN crop c ON c.crop_id = p.crop_id
             JOIN planting_status ps ON ps.status_id = p.status_id
             $whereSql
             ORDER BY ps.status_id, p.planting_date DESC, p.planting_id DESC"
        );
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Category dropdown: [category_id => category_name]
    public function categoryOptions(): array {
        return fetch_lookup($this->pdo, 'crop_category', 'category_id', 'category_name');
    }

    // One row per farm + crop, with planting counts and area
    public function cropsPerFarm($dateFrom, $dateTo, int $farmId = 0, int $cropId = 0, int $categoryId = 0): array {
        $where  = [];
        $params = [];
        add_date_range('p.planting_date', $dateFrom, $dateTo, $where, $params);
        if ($farmId > 0) {
            $where[]            = 'p.farm_id = :farm_id';
            $params[':farm_id'] = $farmId;
        }
        if ($cropId > 0) {
            $where[]            = 'p.crop_id = :crop_id';
            $params[':crop_id'] = $cropId;
        }
        if ($categoryId > 0) {
            $where[]                = 'c.category_id = :category_id';
            $params[':category_id'] = $categoryId;
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmt = $this->pdo->prepare(
            "SELECT fm.farm_id, fm.farm_name, fr.first_name, fr.last_name,
                    c.crop_name, c.variety, cc.category_name,
                    COUNT(p.planting_id) AS planting_count,
                    COALESCE(SUM(p.area_planted), 0) AS total_area,
                    MIN(p.planting_date) AS first_planted,
                    MAX(p.planting_date) AS last_planted
             FROM planting p
             JOIN farm fm ON fm.farm_id = p.farm_id
             JOIN farmer fr ON fr.farmer_id = fm.farmer_id
             JOIN crop c ON c.crop_id = p.crop_id
             LEFT JOIN crop_category cc ON cc.category_id = c.category_id
             $whereSql
             GROUP BY fm.farm_id, fm.farm_name, fr.farmer_id, fr.first_name, fr.last_name,
                      c.crop_id, c.crop_name, c.variety, cc.category_name
             ORDER BY fm.farm_name, fm.farm_id, c.crop_name, c.variety"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

        // Dropdown lookups
    public function soilTypeOptions(): array {
        return fetch_lookup($this->pdo, 'soil_type', 'soil_type_id', 'soil_type_name');
    }

    public function irrigationOptions(): array {
        return fetch_lookup($this->pdo, 'irrigation_type', 'irrigation_id', 'irrigation_name');
    }

    public function gradeOptions(): array {
        return fetch_lookup($this->pdo, 'quality_grade', 'grade_id', 'grade_name', true);
    }

    public function remarkOptions(): array {
        return fetch_lookup($this->pdo, 'harvest_remark', 'remark_id', 'remark_description', true);
    }

    // Farmers with their farms (farmers with no farm still appear)
    public function farmersFarms(string $from, string $to, string $address, int $soilTypeId, int $irrigationId): array {
        $where  = [];
        $params = [];
        add_date_range('fr.date_registered', $from, $to, $where, $params);
        if ($address !== '') {
            $where[]            = 'fr.address ILIKE :address';
            $params[':address'] = '%' . like_escape($address) . '%';
        }
        if ($soilTypeId > 0) {
            $where[]                 = 'fm.soil_type_id = :soil_type_id';
            $params[':soil_type_id'] = $soilTypeId;
        }
        if ($irrigationId > 0) {
            $where[]                  = 'fm.irrigation_id = :irrigation_id';
            $params[':irrigation_id'] = $irrigationId;
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmt = $this->pdo->prepare(
            "SELECT fr.farmer_id, fr.first_name, fr.last_name, fr.contact_number, fr.address,
                    fr.date_registered, fm.farm_id, fm.farm_name, fm.location, fm.area_hectares,
                    st.soil_type_name, ir.irrigation_name
             FROM farmer fr
             LEFT JOIN farm fm ON fm.farmer_id = fr.farmer_id
             LEFT JOIN soil_type st ON st.soil_type_id = fm.soil_type_id
             LEFT JOIN irrigation_type ir ON ir.irrigation_id = fm.irrigation_id
             $whereSql
             ORDER BY fr.last_name, fr.first_name, fr.farmer_id, fm.farm_name"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Individual harvest records
    public function harvestRecords(string $from, string $to, int $farmId, int $cropId, int $gradeId, int $remarkId): array {
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
        if ($gradeId > 0) {
            $where[]             = 'h.grade_id = :grade_id';
            $params[':grade_id'] = $gradeId;
        }
        if ($remarkId > 0) {
            $where[]              = 'h.remark_id = :remark_id';
            $params[':remark_id'] = $remarkId;
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmt = $this->pdo->prepare(
            "SELECT h.harvest_id, h.harvest_date, h.quantity_kg, h.selling_price_per_kg,
                    (h.quantity_kg * h.selling_price_per_kg) AS total_value,
                    c.crop_name, c.variety, fm.farm_name, fr.first_name, fr.last_name,
                    qg.grade_name, hr.remark_description
             FROM harvest h
             JOIN planting p ON p.planting_id = h.planting_id
             JOIN farm fm ON fm.farm_id = p.farm_id
             JOIN farmer fr ON fr.farmer_id = fm.farmer_id
             JOIN crop c ON c.crop_id = p.crop_id
             JOIN harvest_remark hr ON hr.remark_id = h.remark_id
             LEFT JOIN quality_grade qg ON qg.grade_id = h.grade_id
             $whereSql
             ORDER BY h.harvest_date DESC, h.harvest_id DESC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}