<?php
require_once __DIR__ . '/../../includes/functions.php';

// Today's date in Philippine time, as YYYY-MM-DD
if (!function_exists('today_manila')) {
    function today_manila(): string
    {
        return (new DateTime('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d');
    }
}

// True if the text is a real calendar date written as YYYY-MM-DD
if (!function_exists('valid_date_string')) {
    function valid_date_string(string $value): bool
    {
        $date = DateTime::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }
}

// Show a YYYY-MM-DD date as "Nov 11, 2026"
if (!function_exists('format_date')) {
    function format_date(?string $value): string
    {
        if ($value === null || !valid_date_string($value)) {
            return '';
        }
        return DateTime::createFromFormat('!Y-m-d', $value)->format('M j, Y');
    }
}

// A coloured label for a planting status
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

class Planting {
    private PDO $pdo;

    // Only these columns may be used for sorting (never trust ?sort= directly)
    private const SORT_MAP = [
        'id'       => ['p.planting_id'],
        'crop'     => ['c.crop_name', 'c.variety'],
        'farm'     => ['fm.farm_name'],
        'planted'  => ['p.planting_date'],
        'expected' => ['p.expected_harvest_date'],
        'area'     => ['p.area_planted'],
        'status'   => ['ps.status_id'],
        'harvests' => ['harvest_count'],
    ];

    private const FROM_SQL = 'FROM planting p
            JOIN farm fm ON fm.farm_id = p.farm_id
            JOIN farmer fr ON fr.farmer_id = fm.farmer_id
            JOIN crop c ON c.crop_id = p.crop_id
            JOIN planting_status ps ON ps.status_id = p.status_id';

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function isValidSort(string $sort): bool {
        return isset(self::SORT_MAP[$sort]);
    }

    // [status_id => status_name]
    public function fetchStatuses(): array {
        return fetch_lookup($this->pdo, 'planting_status', 'status_id', 'status_name', true);
    }

    // [farm_id => ['label' => 'Farm (Last, First)', 'area' => hectares]]
    public function fetchFarmOptions(): array {
        $rows = $this->pdo->query(
            "SELECT fm.farm_id, fm.farm_name || ' (' || fr.last_name || ', ' || fr.first_name || ')',
                    fm.area_hectares
             FROM farm fm
             JOIN farmer fr ON fr.farmer_id = fm.farmer_id
             ORDER BY fm.farm_name, fr.last_name, fr.first_name"
        )->fetchAll(PDO::FETCH_NUM);

        $options = [];
        foreach ($rows as [$id, $label, $area]) {
            $options[(int) $id] = [
                'label' => $label,
                'area'  => $area === null ? null : (float) $area,
            ];
        }
        return $options;
    }

    // [crop_id => ['label' => 'Rice (Jasmine)', 'days' => growing days]]
    public function fetchCropOptions(): array {
        $rows = $this->pdo->query(
            'SELECT crop_id, crop_name, variety, growing_period_days
             FROM crop ORDER BY crop_name, variety'
        )->fetchAll(PDO::FETCH_NUM);

        $options = [];
        foreach ($rows as [$id, $name, $variety, $days]) {
            $options[(int) $id] = [
                'label' => $name . ($variety !== null && $variety !== '' ? ' (' . $variety . ')' : ''),
                'days'  => $days === null ? null : (int) $days,
            ];
        }
        return $options;
    }

    // $filters: q, farm_id, crop_id, status_id, date_from, date_to
    private function buildWhere(array $filters): array {
        $where  = [];
        $params = [];

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = "(c.crop_name ILIKE :q1 OR c.variety ILIKE :q2 OR fm.farm_name ILIKE :q3
                         OR (fr.first_name || ' ' || fr.last_name) ILIKE :q4)";
            foreach ([':q1', ':q2', ':q3', ':q4'] as $name) {
                $params[$name] = '%' . like_escape($q) . '%';
            }
        }
        if (($filters['farm_id'] ?? 0) > 0) {
            $where[]            = 'p.farm_id = :farm_id';
            $params[':farm_id'] = (int) $filters['farm_id'];
        }
        if (($filters['crop_id'] ?? 0) > 0) {
            $where[]            = 'p.crop_id = :crop_id';
            $params[':crop_id'] = (int) $filters['crop_id'];
        }
        if (($filters['status_id'] ?? 0) > 0) {
            $where[]              = 'p.status_id = :status_id';
            $params[':status_id'] = (int) $filters['status_id'];
        }
        if (($filters['date_from'] ?? '') !== '') {
            $where[]              = 'p.planting_date >= :date_from';
            $params[':date_from'] = $filters['date_from'];
        }
        if (($filters['date_to'] ?? '') !== '') {
            $where[]            = 'p.planting_date <= :date_to';
            $params[':date_to'] = $filters['date_to'];
        }

        return [$where ? 'WHERE ' . implode(' AND ', $where) : '', $params];
    }

    // Number of plantings matching the filters
    public function count(array $filters): int {
        [$whereSql, $params] = $this->buildWhere($filters);

        $stmt = $this->pdo->prepare('SELECT COUNT(*) ' . self::FROM_SQL . " $whereSql");
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function getAll(array $filters, string $sort, string $dir, int $limit, int $offset): array {
        [$whereSql, $params] = $this->buildWhere($filters);

        if (!$this->isValidSort($sort)) {
            $sort = 'planted';
        }
        $dir      = strtolower($dir) === 'asc' ? 'asc' : 'desc';
        $orderSql = implode(', ', array_map(
            fn($column) => "$column $dir NULLS LAST",
            self::SORT_MAP[$sort]
        ));

        $sql = "SELECT p.planting_id, p.planting_date, p.expected_harvest_date, p.area_planted,
                       c.crop_name, c.variety, fm.farm_id, fm.farm_name,
                       fr.first_name, fr.last_name, ps.status_name,
                       (SELECT COUNT(*) FROM harvest h WHERE h.planting_id = p.planting_id) AS harvest_count "
             . self::FROM_SQL . "
                $whereSql
                ORDER BY $orderSql, p.planting_id DESC
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

    // Raw planting columns for pre-filling the edit form, or null if it doesn't exist
    public function findForEdit(int $id): ?array {
        $stmt = $this->pdo->prepare(
            'SELECT farm_id, crop_id, status_id, planting_date, area_planted, seed_quantity, expected_harvest_date
             FROM planting WHERE planting_id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

        // One planting with its crop, farm, owner and status, or null if it doesn't exist
    public function find(int $id): ?array {
        $stmt = $this->pdo->prepare(
            'SELECT p.planting_id, p.planting_date, p.expected_harvest_date, p.area_planted,
                    p.seed_quantity, p.created_at, p.updated_at,
                    c.crop_id, c.crop_name, c.variety,
                    fm.farm_id, fm.farm_name, fr.farmer_id, fr.first_name, fr.last_name,
                    ps.status_name
             ' . self::FROM_SQL . '
             WHERE p.planting_id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    // Harvests recorded for a planting, newest first
    public function fetchHarvests(int $id): array {
        $stmt = $this->pdo->prepare(
            'SELECT h.harvest_id, h.harvest_date, h.quantity_kg, h.selling_price_per_kg,
                    qg.grade_name, hr.remark_description
             FROM harvest h
             JOIN harvest_remark hr ON hr.remark_id = h.remark_id
             LEFT JOIN quality_grade qg ON qg.grade_id = h.grade_id
             WHERE h.planting_id = :id
             ORDER BY h.harvest_date DESC, h.harvest_id DESC'
        );
        $stmt->execute([':id' => $id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Same crop, same farm, same planting date (catches double entries)
    public function existsDuplicate(int $farmId, int $cropId, string $date, ?int $ignoreId = null): bool {
        $sql    = 'SELECT 1 FROM planting
                   WHERE farm_id = :farm_id AND crop_id = :crop_id AND planting_date = :planting_date';
        $params = [':farm_id' => $farmId, ':crop_id' => $cropId, ':planting_date' => $date];

        if ($ignoreId !== null) {
            $sql .= ' AND planting_id <> :id';
            $params[':id'] = $ignoreId;
        }

        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

    public function create(array $clean): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO planting (farm_id, crop_id, status_id, planting_date, area_planted, seed_quantity, expected_harvest_date)
             VALUES (:farm_id, :crop_id, :status_id, :planting_date, :area_planted, :seed_quantity, :expected)
             RETURNING planting_id'
        );
        $stmt->execute([
            ':farm_id'       => $clean['farm_id'],
            ':crop_id'       => $clean['crop_id'],
            ':status_id'     => $clean['status_id'],
            ':planting_date' => $clean['planting_date'],
            ':area_planted'  => $clean['area_planted'],
            ':seed_quantity' => $clean['seed_quantity'],
            ':expected'      => $clean['expected_harvest_date'],
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function update(int $id, array $clean): void {
        $stmt = $this->pdo->prepare(
            'UPDATE planting
             SET farm_id = :farm_id, crop_id = :crop_id, status_id = :status_id, planting_date = :planting_date,
                 area_planted = :area_planted, seed_quantity = :seed_quantity, expected_harvest_date = :expected
             WHERE planting_id = :id'
        );
        $stmt->execute([
            ':farm_id'       => $clean['farm_id'],
            ':crop_id'       => $clean['crop_id'],
            ':status_id'     => $clean['status_id'],
            ':planting_date' => $clean['planting_date'],
            ':area_planted'  => $clean['area_planted'],
            ':seed_quantity' => $clean['seed_quantity'],
            ':expected'      => $clean['expected_harvest_date'],
            ':id'            => $id,
        ]);
    }

    public function delete(int $id): void {
        $stmt = $this->pdo->prepare('DELETE FROM planting WHERE planting_id = :id');
        $stmt->execute([':id' => $id]);
    }

        // "Crop on Farm" label for messages, or null if the planting doesn't exist
    public function findLabel(int $id): ?string {
        $stmt = $this->pdo->prepare(
            "SELECT c.crop_name || ' on ' || fm.farm_name
             FROM planting p
             JOIN crop c ON c.crop_id = p.crop_id
             JOIN farm fm ON fm.farm_id = p.farm_id
             WHERE p.planting_id = :id"
        );
        $stmt->execute([':id' => $id]);
        $name = $stmt->fetchColumn();

        return $name === false ? null : (string) $name;
    }

    // Number of harvest records that depend on this planting
    public function countHarvests(int $id): int {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM harvest WHERE planting_id = :id');
        $stmt->execute([':id' => $id]);

        return (int) $stmt->fetchColumn();
    }
}