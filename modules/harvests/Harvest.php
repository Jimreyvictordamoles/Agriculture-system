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

class Harvest {
    private PDO $pdo;

    // Only these columns may be used for sorting (never trust ?sort= directly)
    private const SORT_MAP = [
        'id'       => ['h.harvest_id'],
        'date'     => ['h.harvest_date'],
        'crop'     => ['c.crop_name', 'c.variety'],
        'farm'     => ['fm.farm_name'],
        'quantity' => ['h.quantity_kg'],
        'price'    => ['h.selling_price_per_kg'],
        'value'    => ['total_value'],
        'grade'    => ['h.grade_id'],
        'remark'   => ['h.remark_id'],
    ];

    private const FROM_SQL = 'FROM harvest h
            JOIN planting p ON p.planting_id = h.planting_id
            JOIN farm fm ON fm.farm_id = p.farm_id
            JOIN farmer fr ON fr.farmer_id = fm.farmer_id
            JOIN crop c ON c.crop_id = p.crop_id
            JOIN harvest_remark hr ON hr.remark_id = h.remark_id
            LEFT JOIN quality_grade qg ON qg.grade_id = h.grade_id';

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function isValidSort(string $sort): bool {
        return isset(self::SORT_MAP[$sort]);
    }

    // Dropdown lists
    public function fetchGrades(): array {
        return fetch_lookup($this->pdo, 'quality_grade', 'grade_id', 'grade_name', true);
    }

    public function fetchRemarks(): array {
        return fetch_lookup($this->pdo, 'harvest_remark', 'remark_id', 'remark_description', true);
    }

    // [farm_id => farm_name] for the filter
    public function fetchFarmOptions(): array {
        $rows = $this->pdo->query('SELECT farm_id, farm_name FROM farm ORDER BY farm_name')
            ->fetchAll(PDO::FETCH_NUM);

        $options = [];
        foreach ($rows as [$id, $name]) {
            $options[(int) $id] = $name;
        }
        return $options;
    }

    // [planting_id => ['label' => '...', 'date' => 'YYYY-MM-DD']]
    public function fetchPlantingOptions(): array {
        $rows = $this->pdo->query(
            'SELECT p.planting_id, p.planting_date, c.crop_name, c.variety, fm.farm_name, ps.status_name
             FROM planting p
             JOIN crop c ON c.crop_id = p.crop_id
             JOIN farm fm ON fm.farm_id = p.farm_id
             JOIN planting_status ps ON ps.status_id = p.status_id
             ORDER BY p.planting_date DESC, p.planting_id DESC'
        )->fetchAll(PDO::FETCH_NUM);

        $options = [];
        foreach ($rows as [$id, $plantingDate, $crop, $variety, $farm, $status]) {
            $cropLabel = $crop . ($variety !== null && $variety !== '' ? ' (' . $variety . ')' : '');
            $options[(int) $id] = [
                'label' => $cropLabel . ' - ' . $farm . ' - planted ' . format_date($plantingDate) . ' - ' . $status,
                'date'  => $plantingDate,
            ];
        }
        return $options;
    }

    // $filters: q, farm_id, grade_id, remark_id, date_from, date_to
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
        if (($filters['grade_id'] ?? 0) > 0) {
            $where[]             = 'h.grade_id = :grade_id';
            $params[':grade_id'] = (int) $filters['grade_id'];
        }
        if (($filters['remark_id'] ?? 0) > 0) {
            $where[]              = 'h.remark_id = :remark_id';
            $params[':remark_id'] = (int) $filters['remark_id'];
        }
        if (($filters['date_from'] ?? '') !== '') {
            $where[]              = 'h.harvest_date >= :date_from';
            $params[':date_from'] = $filters['date_from'];
        }
        if (($filters['date_to'] ?? '') !== '') {
            $where[]            = 'h.harvest_date <= :date_to';
            $params[':date_to'] = $filters['date_to'];
        }

        return [$where ? 'WHERE ' . implode(' AND ', $where) : '', $params];
    }

    // Row count and totals for the current filters: ['count' => int, 'total_kg' => float, 'total_value' => float]
    public function summary(array $filters): array {
        [$whereSql, $params] = $this->buildWhere($filters);

        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*), COALESCE(SUM(h.quantity_kg), 0),
                    COALESCE(SUM(h.quantity_kg * h.selling_price_per_kg), 0) '
            . self::FROM_SQL . " $whereSql"
        );
        $stmt->execute($params);
        [$count, $totalKg, $totalValue] = $stmt->fetch(PDO::FETCH_NUM);

        return [
            'count'       => (int) $count,
            'total_kg'    => (float) $totalKg,
            'total_value' => (float) $totalValue,
        ];
    }

    public function getAll(array $filters, string $sort, string $dir, int $limit, int $offset): array {
        [$whereSql, $params] = $this->buildWhere($filters);

        if (!$this->isValidSort($sort)) {
            $sort = 'date';
        }
        $dir      = strtolower($dir) === 'asc' ? 'asc' : 'desc';
        $orderSql = implode(', ', array_map(
            fn($column) => "$column $dir NULLS LAST",
            self::SORT_MAP[$sort]
        ));

        $sql = "SELECT h.harvest_id, h.harvest_date, h.quantity_kg, h.selling_price_per_kg,
                       (h.quantity_kg * h.selling_price_per_kg) AS total_value,
                       c.crop_name, c.variety, fm.farm_id, fm.farm_name,
                       qg.grade_name, hr.remark_description "
             . self::FROM_SQL . "
                $whereSql
                ORDER BY $orderSql, h.harvest_id DESC
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

    // One harvest with its crop, farm, owner, grade and remark for view.php, or null if it doesn't exist
    public function find(int $id): ?array {
        $stmt = $this->pdo->prepare(
            'SELECT h.harvest_id, h.harvest_date, h.quantity_kg, h.selling_price_per_kg,
                    (h.quantity_kg * h.selling_price_per_kg) AS total_value,
                    h.created_at, h.updated_at,
                    p.planting_id, p.planting_date,
                    c.crop_id, c.crop_name, c.variety,
                    fm.farm_id, fm.farm_name, fr.farmer_id, fr.first_name, fr.last_name,
                    qg.grade_name, hr.remark_description '
            . self::FROM_SQL . '
             WHERE h.harvest_id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    // Raw harvest columns for pre-filling the edit form, or null if it doesn't exist
    public function findForEdit(int $id): ?array {
        $stmt = $this->pdo->prepare(
            'SELECT planting_id, harvest_date, quantity_kg, selling_price_per_kg, grade_id, remark_id
             FROM harvest WHERE harvest_id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    // Same planting, same date, same quantity (catches double entries)
    public function existsDuplicate(int $plantingId, string $date, string $quantity, ?int $ignoreId = null): bool {
        $sql    = 'SELECT 1 FROM harvest
                   WHERE planting_id = :planting_id AND harvest_date = :harvest_date AND quantity_kg = :quantity';
        $params = [':planting_id' => $plantingId, ':harvest_date' => $date, ':quantity' => $quantity];

        if ($ignoreId !== null) {
            $sql .= ' AND harvest_id <> :id';
            $params[':id'] = $ignoreId;
        }

        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

    public function create(array $clean): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO harvest (planting_id, harvest_date, quantity_kg, selling_price_per_kg, grade_id, remark_id)
             VALUES (:planting_id, :harvest_date, :quantity, :price, :grade_id, :remark_id)
             RETURNING harvest_id'
        );
        $stmt->execute([
            ':planting_id'  => $clean['planting_id'],
            ':harvest_date' => $clean['harvest_date'],
            ':quantity'     => $clean['quantity_kg'],
            ':price'        => $clean['selling_price_per_kg'],
            ':grade_id'     => $clean['grade_id'],
            ':remark_id'    => $clean['remark_id'],
        ]);

        return (int) $stmt->fetchColumn();
    }

    // Looks up the id of the "Harvested" planting status, or null if it doesn't exist
    private function harvestedStatusId(): ?int {
        $id = $this->pdo->query("SELECT status_id FROM planting_status WHERE status_name = 'Harvested'")
            ->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    // Saves a harvest and, if asked, marks its planting as Harvested. Both succeed together or not at all.
    public function createAndMarkPlanting(array $clean, bool $markHarvested): int {
        $this->pdo->beginTransaction();

        try {
            $id = $this->create($clean);

            if ($markHarvested) {
                $statusId = $this->harvestedStatusId();
                if ($statusId !== null) {
                    $stmt = $this->pdo->prepare('UPDATE planting SET status_id = :status_id WHERE planting_id = :planting_id');
                    $stmt->execute([':status_id' => $statusId, ':planting_id' => $clean['planting_id']]);
                }
            }

            $this->pdo->commit();
            return $id;
        } catch (Throwable $ex) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $ex;
        }
    }

    public function update(int $id, array $clean): void {
        $stmt = $this->pdo->prepare(
            'UPDATE harvest
             SET planting_id = :planting_id, harvest_date = :harvest_date, quantity_kg = :quantity,
                 selling_price_per_kg = :price, grade_id = :grade_id, remark_id = :remark_id
             WHERE harvest_id = :id'
        );
        $stmt->execute([
            ':planting_id'  => $clean['planting_id'],
            ':harvest_date' => $clean['harvest_date'],
            ':quantity'     => $clean['quantity_kg'],
            ':price'        => $clean['selling_price_per_kg'],
            ':grade_id'     => $clean['grade_id'],
            ':remark_id'    => $clean['remark_id'],
            ':id'           => $id,
        ]);
    }

    public function delete(int $id): void {
        $stmt = $this->pdo->prepare('DELETE FROM harvest WHERE harvest_id = :id');
        $stmt->execute([':id' => $id]);
    }
}