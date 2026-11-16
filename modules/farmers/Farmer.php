<?php
require_once __DIR__ . '/../../includes/functions.php';

class Farmer {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function find(int $id): ?array {
        $stmt = $this->pdo->prepare(
            'SELECT farmer_id, first_name, last_name, contact_number, address,
                    date_registered, created_at, updated_at
             FROM farmer WHERE farmer_id = :id'
        );
        $stmt->execute([':id' => $id]);
        $farmer = $stmt->fetch(PDO::FETCH_ASSOC);

        return $farmer === false ? null : $farmer;
    }

    public function getFarms(int $farmerId): array {
        $stmt = $this->pdo->prepare(
            'SELECT farm_id, farm_name, location, area_hectares
             FROM farm WHERE farmer_id = :id ORDER BY farm_name'
        );
        $stmt->execute([':id' => $farmerId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

        // Only these columns may be used for sorting (never trust ?sort= directly)
    private const SORT_MAP = [
        'id'         => ['f.farmer_id'],
        'name'       => ['f.last_name', 'f.first_name'],
        'contact'    => ['f.contact_number'],
        'address'    => ['f.address'],
        'registered' => ['f.date_registered'],
        'farms'      => ['farm_count'],
    ];

    public function isValidSort(string $sort): bool {
        return isset(self::SORT_MAP[$sort]);
    }

    private function buildWhere(string $q, string $dateFrom, string $dateTo): array {
        $where  = [];
        $params = [];

        if ($q !== '') {
            $where[] = "(f.first_name ILIKE :q1 OR f.last_name ILIKE :q2
                         OR (f.first_name || ' ' || f.last_name) ILIKE :q3
                         OR f.contact_number ILIKE :q4 OR f.address ILIKE :q5)";
            foreach ([':q1', ':q2', ':q3', ':q4', ':q5'] as $name) {
                $params[$name] = '%' . like_escape($q) . '%';
            }
        }
        if ($dateFrom !== '') {
            $where[]              = 'f.date_registered >= :date_from';
            $params[':date_from'] = $dateFrom;
        }
        if ($dateTo !== '') {
            $where[]            = 'f.date_registered <= :date_to';
            $params[':date_to'] = $dateTo;
        }

        return [$where ? 'WHERE ' . implode(' AND ', $where) : '', $params];
    }

    public function count(string $q, string $dateFrom, string $dateTo): int {
        [$whereSql, $params] = $this->buildWhere($q, $dateFrom, $dateTo);

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM farmer f $whereSql");
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function getAll(string $q, string $dateFrom, string $dateTo, string $sort, string $dir, int $limit, int $offset): array {
        [$whereSql, $params] = $this->buildWhere($q, $dateFrom, $dateTo);

        if (!$this->isValidSort($sort)) {
            $sort = 'name';
        }
        $dir      = $dir === 'desc' ? 'desc' : 'asc';
        $orderSql = implode(', ', array_map(fn($column) => "$column $dir NULLS LAST", self::SORT_MAP[$sort]));

        $sql = "SELECT f.farmer_id, f.first_name, f.last_name, f.contact_number, f.address,
                       f.date_registered,
                       (SELECT COUNT(*) FROM farm fm WHERE fm.farmer_id = f.farmer_id) AS farm_count
                FROM farmer f
                $whereSql
                ORDER BY $orderSql, f.farmer_id ASC
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

        public function existsByContact(string $contact, ?int $ignoreId = null): bool {
        $sql    = 'SELECT 1 FROM farmer WHERE contact_number = :contact';
        $params = [':contact' => $contact];

        if ($ignoreId !== null) {
            $sql .= ' AND farmer_id <> :id';
            $params[':id'] = $ignoreId;
        }

        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

        public function create(array $clean): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO farmer (first_name, last_name, contact_number, address, date_registered)
             VALUES (:first_name, :last_name, :contact, :address, :registered)
             RETURNING farmer_id'
        );
        $stmt->execute([
            ':first_name' => $clean['first_name'],
            ':last_name'  => $clean['last_name'],
            ':contact'    => $clean['contact_number'],
            ':address'    => $clean['address'],
            ':registered' => $clean['date_registered'],
        ]);

        return (int) $stmt->fetchColumn();
    }

        public function findForEdit(int $id): ?array {
        $stmt = $this->pdo->prepare(
            'SELECT first_name, last_name, contact_number, address, date_registered
             FROM farmer WHERE farmer_id = :id'
        );
        $stmt->execute([':id' => $id]);
        $farmer = $stmt->fetch(PDO::FETCH_ASSOC);

        return $farmer === false ? null : $farmer;
    }

    public function update(int $id, array $clean): void {
        $stmt = $this->pdo->prepare(
            'UPDATE farmer
             SET first_name = :first_name, last_name = :last_name, contact_number = :contact,
                 address = :address, date_registered = :registered
             WHERE farmer_id = :id'
        );
        $stmt->execute([
            ':first_name' => $clean['first_name'],
            ':last_name'  => $clean['last_name'],
            ':contact'    => $clean['contact_number'],
            ':address'    => $clean['address'],
            ':registered' => $clean['date_registered'],
            ':id'         => $id,
        ]);
    }

        public function getName(int $id): ?string {
        $stmt = $this->pdo->prepare(
            "SELECT first_name || ' ' || last_name FROM farmer WHERE farmer_id = :id"
        );
        $stmt->execute([':id' => $id]);
        $name = $stmt->fetchColumn();

        return $name === false ? null : (string) $name;
    }

    public function countFarms(int $id): int {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM farm WHERE farmer_id = :id');
        $stmt->execute([':id' => $id]);

        return (int) $stmt->fetchColumn();
    }

    public function delete(int $id): void {
        $stmt = $this->pdo->prepare('DELETE FROM farmer WHERE farmer_id = :id');
        $stmt->execute([':id' => $id]);
    }
}