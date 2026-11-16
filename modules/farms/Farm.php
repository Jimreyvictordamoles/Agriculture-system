<?php
require_once __DIR__ . '/../../includes/functions.php';

class Farm {
    private PDO $pdo;

    // Only these columns may be used for sorting (never trust ?sort= directly)
    private const SORT_MAP = [
        'id'         => ['fm.farm_id'],
        'farm'       => ['fm.farm_name'],
        'owner'      => ['fr.last_name', 'fr.first_name'],
        'location'   => ['fm.location'],
        'area'       => ['fm.area_hectares'],
        'soil'       => ['st.soil_type_name'],
        'irrigation' => ['ir.irrigation_name'],
        'plantings'  => ['planting_count'],
    ];

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    // Dropdown list of farmers: [farmer_id => "Last, First"]
    public function fetchFarmerOptions(): array {
        $rows = $this->pdo->query(
            "SELECT farmer_id, last_name || ', ' || first_name
             FROM farmer ORDER BY last_name, first_name"
        )->fetchAll(PDO::FETCH_NUM);

        $options = [];
        foreach ($rows as [$id, $name]) {
            $options[(int) $id] = $name;
        }
        return $options;
    }

    // [soil_type_id => soil_type_name]
    public function fetchSoilTypes(): array {
        $rows = $this->pdo->query(
            'SELECT soil_type_id, soil_type_name FROM soil_type ORDER BY soil_type_name'
        )->fetchAll(PDO::FETCH_NUM);

        $options = [];
        foreach ($rows as [$id, $name]) {
            $options[(int) $id] = $name;
        }
        return $options;
    }

    // [irrigation_id => irrigation_name]
    public function fetchIrrigationTypes(): array {
        $rows = $this->pdo->query(
            'SELECT irrigation_id, irrigation_name FROM irrigation_type ORDER BY irrigation_name'
        )->fetchAll(PDO::FETCH_NUM);

        $options = [];
        foreach ($rows as [$id, $name]) {
            $options[(int) $id] = $name;
        }
        return $options;
    }

    // True if this owner already has a farm with that name (case-insensitive)
    public function existsByOwnerAndName(int $farmerId, string $name, ?int $ignoreId = null): bool {
        $sql    = 'SELECT 1 FROM farm WHERE farmer_id = :farmer_id AND LOWER(farm_name) = LOWER(:name)';
        $params = [':farmer_id' => $farmerId, ':name' => $name];

        if ($ignoreId !== null) {
            $sql .= ' AND farm_id <> :id';
            $params[':id'] = $ignoreId;
        }

        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

    public function create(array $clean): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO farm (farmer_id, farm_name, location, area_hectares, soil_type_id, irrigation_id)
             VALUES (:farmer_id, :farm_name, :location, :area, :soil_type_id, :irrigation_id)
             RETURNING farm_id'
        );
        $stmt->execute([
            ':farmer_id'     => $clean['farmer_id'],
            ':farm_name'     => $clean['farm_name'],
            ':location'      => $clean['location'],
            ':area'          => $clean['area_hectares'],
            ':soil_type_id'  => $clean['soil_type_id'],
            ':irrigation_id' => $clean['irrigation_id'],
        ]);

        return (int) $stmt->fetchColumn();
    }

    // One farm with its owner, soil type and irrigation names, or null if it doesn't exist
    public function find(int $id): ?array {
        $stmt = $this->pdo->prepare(
            'SELECT fm.farm_id, fm.farm_name, fm.location, fm.area_hectares, fm.created_at, fm.updated_at,
                    fr.farmer_id, fr.first_name, fr.last_name,
                    st.soil_type_name, ir.irrigation_name
             FROM farm fm
             JOIN farmer fr ON fr.farmer_id = fm.farmer_id
             LEFT JOIN soil_type st ON st.soil_type_id = fm.soil_type_id
             LEFT JOIN irrigation_type ir ON ir.irrigation_id = fm.irrigation_id
             WHERE fm.farm_id = :id'
        );
        $stmt->execute([':id' => $id]);
        $farm = $stmt->fetch(PDO::FETCH_ASSOC);

        return $farm === false ? null : $farm;
    }

    // Plantings on one farm, newest first
    public function getPlantings(int $farmId): array {
        $stmt = $this->pdo->prepare(
            'SELECT p.planting_id, c.crop_name, c.variety, p.planting_date, ps.status_name
             FROM planting p
             JOIN crop c ON c.crop_id = p.crop_id
             JOIN planting_status ps ON ps.status_id = p.status_id
             WHERE p.farm_id = :id
             ORDER BY p.planting_date DESC, p.planting_id DESC'
        );
        $stmt->execute([':id' => $farmId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Raw farm columns for pre-filling the edit form, or null if it doesn't exist
    public function findForEdit(int $id): ?array {
        $stmt = $this->pdo->prepare(
            'SELECT farmer_id, farm_name, location, area_hectares, soil_type_id, irrigation_id
             FROM farm WHERE farm_id = :id'
        );
        $stmt->execute([':id' => $id]);
        $farm = $stmt->fetch(PDO::FETCH_ASSOC);

        return $farm === false ? null : $farm;
    }

    public function update(int $id, array $clean): void {
        $stmt = $this->pdo->prepare(
            'UPDATE farm
             SET farmer_id = :farmer_id, farm_name = :farm_name, location = :location,
                 area_hectares = :area, soil_type_id = :soil_type_id, irrigation_id = :irrigation_id
             WHERE farm_id = :id'
        );
        $stmt->execute([
            ':farmer_id'     => $clean['farmer_id'],
            ':farm_name'     => $clean['farm_name'],
            ':location'      => $clean['location'],
            ':area'          => $clean['area_hectares'],
            ':soil_type_id'  => $clean['soil_type_id'],
            ':irrigation_id' => $clean['irrigation_id'],
            ':id'            => $id,
        ]);
    }

    public function getName(int $id): ?string {
        $stmt = $this->pdo->prepare('SELECT farm_name FROM farm WHERE farm_id = :id');
        $stmt->execute([':id' => $id]);
        $name = $stmt->fetchColumn();

        return $name === false ? null : (string) $name;
    }

    public function countPlantings(int $id): int {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM planting WHERE farm_id = :id');
        $stmt->execute([':id' => $id]);

        return (int) $stmt->fetchColumn();
    }

    public function delete(int $id): void {
        $stmt = $this->pdo->prepare('DELETE FROM farm WHERE farm_id = :id');
        $stmt->execute([':id' => $id]);
    }

    public function isValidSort(string $sort): bool {
        return isset(self::SORT_MAP[$sort]);
    }

    // Builds the WHERE clause from the filters: q, farmer_id, soil_type_id, irrigation_id
    private function buildWhere(array $filters): array {
        $where  = [];
        $params = [];

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = "(fm.farm_name ILIKE :q1 OR fm.location ILIKE :q2
                         OR (fr.first_name || ' ' || fr.last_name) ILIKE :q3)";
            foreach ([':q1', ':q2', ':q3'] as $name) {
                $params[$name] = '%' . like_escape($q) . '%';
            }
        }
        if (($filters['farmer_id'] ?? 0) > 0) {
            $where[]              = 'fm.farmer_id = :farmer_id';
            $params[':farmer_id'] = (int) $filters['farmer_id'];
        }
        if (($filters['soil_type_id'] ?? 0) > 0) {
            $where[]                 = 'fm.soil_type_id = :soil_type_id';
            $params[':soil_type_id'] = (int) $filters['soil_type_id'];
        }
        if (($filters['irrigation_id'] ?? 0) > 0) {
            $where[]                  = 'fm.irrigation_id = :irrigation_id';
            $params[':irrigation_id'] = (int) $filters['irrigation_id'];
        }

        return [$where ? 'WHERE ' . implode(' AND ', $where) : '', $params];
    }

    public function count(array $filters): int {
        [$whereSql, $params] = $this->buildWhere($filters);

        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM farm fm JOIN farmer fr ON fr.farmer_id = fm.farmer_id $whereSql"
        );
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function getAll(array $filters, string $sort, string $dir, int $limit, int $offset): array {
        [$whereSql, $params] = $this->buildWhere($filters);

        if (!$this->isValidSort($sort)) {
            $sort = 'farm';
        }
        $dir      = strtolower($dir) === 'desc' ? 'desc' : 'asc';
        $orderSql = implode(', ', array_map(
            fn($column) => "$column $dir NULLS LAST",
            self::SORT_MAP[$sort]
        ));

        $stmt = $this->pdo->prepare(
            "SELECT fm.farm_id, fm.farm_name, fm.location, fm.area_hectares,
                    fr.farmer_id, fr.first_name, fr.last_name,
                    st.soil_type_name, ir.irrigation_name,
                    (SELECT COUNT(*) FROM planting p WHERE p.farm_id = fm.farm_id) AS planting_count
             FROM farm fm
             JOIN farmer fr ON fr.farmer_id = fm.farmer_id
             LEFT JOIN soil_type st ON st.soil_type_id = fm.soil_type_id
             LEFT JOIN irrigation_type ir ON ir.irrigation_id = fm.irrigation_id
             $whereSql
             ORDER BY $orderSql, fm.farm_id ASC
             LIMIT :limit OFFSET :offset"
        );

        foreach ($params as $name => $value) {
            $stmt->bindValue($name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}