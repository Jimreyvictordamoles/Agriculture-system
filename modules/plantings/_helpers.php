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

// Dropdown list of farms: [farm_id => ['label' => 'Farm (Last, First)', 'area' => hectares]]
function fetch_farm_options(PDO $pdo): array
{
    $rows = $pdo->query(
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

// Dropdown list of crops: [crop_id => ['label' => 'Rice (Jasmine)', 'days' => growing days]]
function fetch_crop_options(PDO $pdo): array
{
    $rows = $pdo->query(
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

// Checks the planting form. Returns:
//   errors - a list of messages to show the user
//   values - what to show again in the form (text, as typed)
//   clean  - what to save in the database
function validate_planting(
    array $input,
    array $farms,
    array $crops,
    array $statuses,
    PDO $pdo,
    ?int $ignoreId = null
): array {
    $errors = [];

    $values = [
        'farm_id'               => trim((string) ($input['farm_id'] ?? '')),
        'crop_id'               => trim((string) ($input['crop_id'] ?? '')),
        'status_id'             => trim((string) ($input['status_id'] ?? '')),
        'planting_date'         => trim((string) ($input['planting_date'] ?? '')),
        'area_planted'          => trim((string) ($input['area_planted'] ?? '')),
        'seed_quantity'         => trim((string) ($input['seed_quantity'] ?? '')),
        'expected_harvest_date' => trim((string) ($input['expected_harvest_date'] ?? '')),
    ];

    $clean = [
        'farm_id'               => null,
        'crop_id'               => null,
        'status_id'             => null,
        'planting_date'         => $values['planting_date'],
        'area_planted'          => null,
        'seed_quantity'         => null,
        'expected_harvest_date' => $values['expected_harvest_date'] === '' ? null : $values['expected_harvest_date'],
    ];

    // Dropdowns: the chosen ID must really exist in its table
    $dropdowns = [
        ['farm_id',   $farms,    'a farm'],
        ['crop_id',   $crops,    'a crop'],
        ['status_id', $statuses, 'a status'],
    ];
    foreach ($dropdowns as [$field, $options, $label]) {
        if ($values[$field] === '') {
            $errors[] = 'Please choose ' . $label . '.';
        } elseif (ctype_digit($values[$field]) && isset($options[(int) $values[$field]])) {
            $clean[$field] = (int) $values[$field];
        } else {
            $errors[] = 'Please choose a valid option for ' . $label . '.';
        }
    }

    // Planting date
    $plantingDateOk = false;
    if ($values['planting_date'] === '') {
        $errors[] = 'Planting date is required.';
    } elseif (!valid_date_string($values['planting_date'])) {
        $errors[] = 'Planting date is not a valid date.';
    } elseif ($values['planting_date'] > today_manila()) {
        $errors[] = 'Planting date cannot be in the future.';
    } else {
        $plantingDateOk = true;
    }

    // Expected harvest date (optional, but never before the planting date)
    if ($values['expected_harvest_date'] !== '') {
        if (!valid_date_string($values['expected_harvest_date'])) {
            $errors[] = 'Expected harvest date is not a valid date.';
        } elseif ($plantingDateOk && $values['expected_harvest_date'] < $values['planting_date']) {
            $errors[] = 'Expected harvest date cannot be before the planting date.';
        }
    }

    // Area planted must be a positive number and fit inside the farm
    if ($values['area_planted'] === '') {
        $errors[] = 'Area planted (hectares) is required.';
    } elseif (!preg_match('/^\d+(\.\d{1,2})?$/', $values['area_planted'])
        || (float) $values['area_planted'] <= 0
        || (float) $values['area_planted'] > 100000) {
        $errors[] = 'Area planted must be a number above 0 and up to 100000, with at most 2 decimal places.';
    } else {
        $clean['area_planted'] = $values['area_planted'];

        $farmArea = $clean['farm_id'] !== null ? ($farms[$clean['farm_id']]['area'] ?? null) : null;
        if ($farmArea !== null && (float) $values['area_planted'] > $farmArea + 0.00001) {
            $errors[] = 'Area planted (' . $values['area_planted'] . ' ha) cannot be larger than the farm\'s total area ('
                . number_format($farmArea, 2) . ' ha).';
        }
    }

    // Seed quantity: zero is allowed, negative is not
    if ($values['seed_quantity'] === '') {
        $errors[] = 'Seed quantity is required.';
    } elseif (!preg_match('/^\d+(\.\d{1,2})?$/', $values['seed_quantity'])
        || (float) $values['seed_quantity'] > 1000000) {
        $errors[] = 'Seed quantity must be a number from 0 to 1000000, with at most 2 decimal places.';
    } else {
        $clean['seed_quantity'] = $values['seed_quantity'];
    }

    // The same crop cannot be recorded twice on the same farm on the same day
    if (!$errors) {
        $sql    = 'SELECT 1 FROM planting
                   WHERE farm_id = :farm_id AND crop_id = :crop_id AND planting_date = :planting_date';
        $params = [
            ':farm_id'       => $clean['farm_id'],
            ':crop_id'       => $clean['crop_id'],
            ':planting_date' => $clean['planting_date'],
        ];

        if ($ignoreId !== null) {
            $sql .= ' AND planting_id <> :id';
            $params[':id'] = $ignoreId;
        }

        $stmt = $pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        if ($stmt->fetchColumn()) {
            $errors[] = 'This crop is already recorded on this farm for the same planting date.';
        }
    }

    return ['errors' => $errors, 'values' => $values, 'clean' => $clean];
}
