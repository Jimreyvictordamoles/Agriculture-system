<?php
require_once __DIR__ . '/../../includes/functions.php';

class Crop {
    private PDO $pdo;

    // Only these columns may be used for sorting (never trust ?sort= directly)
    private array $sortMap = [
        'id'       => 'c.crop_id',
        'name'     => 'c.crop_name',
        'variety'  => 'c.variety',
        'category' => 'cc.category_name',
        'season'   => 's.season_name',
        'days'     => 'c.growing_period_days',
    ];

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function isValidSort(string $sort): bool {
        return isset($this->sortMap[$sort]);
    }

    public function getCategories(): array {
        return fetch_lookup($this->pdo, 'crop_category', 'category_id', 'category_name');
    }

    public function getSeasons(): array {
        return fetch_lookup($this->pdo, 'season', 'season_id', 'season_name');
    }

    private function buildWhere(string $q, int $categoryId, int $seasonId): array {
        $where  = [];
        $params = [];

        if ($q !== '') {
            $where[]       = '(c.crop_name ILIKE :q1 OR c.variety ILIKE :q2)';
            $params[':q1'] = '%' . like_escape($q) . '%';
            $params[':q2'] = '%' . like_escape($q) . '%';
        }
        if ($categoryId > 0) {
            $where[]                = 'c.category_id = :category_id';
            $params[':category_id'] = $categoryId;
        }
        if ($seasonId > 0) {
            $where[]              = 'c.season_id = :season_id';
            $params[':season_id'] = $seasonId;
        }

        return [$where ? 'WHERE ' . implode(' AND ', $where) : '', $params];
    }

    public function count(string $q, int $categoryId, int $seasonId): int {
        [$whereSql, $params] = $this->buildWhere($q, $categoryId, $seasonId);

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM crop c $whereSql");
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function getAll(
        string $q,
        int $categoryId,
        int $seasonId,
        string $sort,
        string $dir,
        int $limit,
        int $offset
    ): array {
        [$whereSql, $params] = $this->buildWhere($q, $categoryId, $seasonId);

        $orderBy = $this->sortMap[$sort] ?? $this->sortMap['name'];
        $dir     = (strtolower($dir) === 'desc') ? 'DESC' : 'ASC';

        $sql = "SELECT c.crop_id, c.crop_name, c.variety, c.growing_period_days,
                       cc.category_name, s.season_name
                FROM crop c
                LEFT JOIN crop_category cc ON cc.category_id = c.category_id
                LEFT JOIN season s ON s.season_id = c.season_id
                $whereSql
                ORDER BY $orderBy $dir NULLS LAST, c.crop_id ASC
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

    public function find(int $id): ?array {
        $stmt = $this->pdo->prepare(
            'SELECT c.crop_id, c.crop_name, c.variety, c.growing_period_days,
                    c.created_at, c.updated_at, cc.category_name, s.season_name,
                    (SELECT COUNT(*) FROM planting p WHERE p.crop_id = c.crop_id) AS planting_count
             FROM crop c
             LEFT JOIN crop_category cc ON cc.category_id = c.category_id
             LEFT JOIN season s ON s.season_id = c.season_id
             WHERE c.crop_id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findForEdit(int $id): ?array {
        $stmt = $this->pdo->prepare(
            'SELECT crop_name, variety, category_id, season_id, growing_period_days
             FROM crop WHERE crop_id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    // True when another crop has the same name and variety (ignores case and extra spaces)
    public function existsByNameVariety(string $name, ?string $variety, ?int $ignoreId = null): bool {
        $sql = "SELECT 1 FROM crop
                WHERE LOWER(TRIM(crop_name)) = LOWER(TRIM(:name))
                  AND LOWER(TRIM(COALESCE(variety, ''))) = LOWER(TRIM(:variety))";
        $params = [
            ':name'    => $name,
            ':variety' => $variety ?? '',
        ];

        if ($ignoreId !== null) {   // when editing, don't count the crop itself
            $sql .= ' AND crop_id <> :id';
            $params[':id'] = $ignoreId;
        }

        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

    public function create(array $clean): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO crop (crop_name, variety, category_id, season_id, growing_period_days)
             VALUES (:name, :variety, :category_id, :season_id, :days)'
        );
        $stmt->execute([
            ':name'        => $clean['crop_name'],
            ':variety'     => $clean['variety'],
            ':category_id' => $clean['category_id'],
            ':season_id'   => $clean['season_id'],
            ':days'        => $clean['growing_period_days'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $clean): void {
        $stmt = $this->pdo->prepare(
            'UPDATE crop
             SET crop_name = :name, variety = :variety, category_id = :category_id,
                 season_id = :season_id, growing_period_days = :days
             WHERE crop_id = :id'
        );
        $stmt->execute([
            ':name'        => $clean['crop_name'],
            ':variety'     => $clean['variety'],
            ':category_id' => $clean['category_id'],
            ':season_id'   => $clean['season_id'],
            ':days'        => $clean['growing_period_days'],
            ':id'          => $id,
        ]);
    }

    public function getName(int $id): ?string {
        $stmt = $this->pdo->prepare('SELECT crop_name FROM crop WHERE crop_id = :id');
        $stmt->execute([':id' => $id]);
        $name = $stmt->fetchColumn();

        return $name === false ? null : (string) $name;
    }

    public function countPlantings(int $id): int {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM planting WHERE crop_id = :id');
        $stmt->execute([':id' => $id]);

        return (int) $stmt->fetchColumn();
    }

    public function delete(int $id): void {
        $stmt = $this->pdo->prepare('DELETE FROM crop WHERE crop_id = :id');
        $stmt->execute([':id' => $id]);
    }
}