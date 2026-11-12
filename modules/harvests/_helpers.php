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

// Dropdown list of plantings:
// [planting_id => ['label' => 'Rice (Jasmine) - Sunrise Farm - planted Oct 1, 2026 - Growing', 'date' => '2026-10-01']]
function fetch_planting_options(PDO $pdo): array
{
    $rows = $pdo->query(
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

// Dropdown list of farms for the filter: [farm_id => farm_name]
function fetch_harvest_farm_options(PDO $pdo): array
{
    $rows = $pdo->query('SELECT farm_id, farm_name FROM farm ORDER BY farm_name')->fetchAll(PDO::FETCH_NUM);

    $options = [];
    foreach ($rows as [$id, $name]) {
        $options[(int) $id] = $name;
    }
    return $options;
}

// Checks the harvest form. Returns:
//   errors - a list of messages to show the user
//   values - what to show again in the form (text, as typed)
//   clean  - what to save in the database
function validate_harvest(
    array $input,
    array $plantings,
    array $grades,
    array $remarks,
    PDO $pdo,
    ?int $ignoreId = null
): array {
    $errors = [];

    $values = [
        'planting_id'          => trim((string) ($input['planting_id'] ?? '')),
        'harvest_date'         => trim((string) ($input['harvest_date'] ?? '')),
        'quantity_kg'          => trim((string) ($input['quantity_kg'] ?? '')),
        'selling_price_per_kg' => trim((string) ($input['selling_price_per_kg'] ?? '')),
        'grade_id'             => trim((string) ($input['grade_id'] ?? '')),
        'remark_id'            => trim((string) ($input['remark_id'] ?? '')),
    ];

    $clean = [
        'planting_id'          => null,
        'harvest_date'         => $values['harvest_date'],
        'quantity_kg'          => null,
        'selling_price_per_kg' => null,
        'grade_id'             => null,
        'remark_id'            => null,
    ];

    // Dropdowns: the chosen ID must really exist in its table
    $dropdowns = [
        ['planting_id', $plantings, 'a planting'],
        ['grade_id',    $grades,    'a quality grade'],
        ['remark_id',   $remarks,   'a remark'],
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

    // Harvest date: real date, not in the future, not before the planting date
    if ($values['harvest_date'] === '') {
        $errors[] = 'Harvest date is required.';
    } elseif (!valid_date_string($values['harvest_date'])) {
        $errors[] = 'Harvest date is not a valid date.';
    } elseif ($values['harvest_date'] > today_manila()) {
        $errors[] = 'Harvest date cannot be in the future.';
    } elseif ($clean['planting_id'] !== null) {
        $plantingDate = $plantings[$clean['planting_id']]['date'] ?? null;
        if ($plantingDate !== null && $values['harvest_date'] < $plantingDate) {
            $errors[] = 'Harvest date cannot be before the planting date (' . format_date($plantingDate) . ').';
        }
    }

    // Quantity must be above zero
    if ($values['quantity_kg'] === '') {
        $errors[] = 'Quantity (kg) is required.';
    } elseif (!preg_match('/^\d+(\.\d{1,2})?$/', $values['quantity_kg'])
        || (float) $values['quantity_kg'] <= 0
        || (float) $values['quantity_kg'] > 1000000) {
        $errors[] = 'Quantity must be a number above 0 and up to 1000000, with at most 2 decimal places.';
    } else {
        $clean['quantity_kg'] = $values['quantity_kg'];
    }

    // Price: zero is allowed, negative is not
    if ($values['selling_price_per_kg'] === '') {
        $errors[] = 'Selling price per kg is required.';
    } elseif (!preg_match('/^\d+(\.\d{1,2})?$/', $values['selling_price_per_kg'])
        || (float) $values['selling_price_per_kg'] > 100000) {
        $errors[] = 'Selling price must be a number from 0 to 100000, with at most 2 decimal places.';
    } else {
        $clean['selling_price_per_kg'] = $values['selling_price_per_kg'];
    }

    // Catches double entries: same planting, same date, same quantity
    if (!$errors) {
        $sql    = 'SELECT 1 FROM harvest
                   WHERE planting_id = :planting_id AND harvest_date = :harvest_date AND quantity_kg = :quantity';
        $params = [
            ':planting_id'  => $clean['planting_id'],
            ':harvest_date' => $clean['harvest_date'],
            ':quantity'     => $clean['quantity_kg'],
        ];

        if ($ignoreId !== null) {
            $sql .= ' AND harvest_id <> :id';
            $params[':id'] = $ignoreId;
        }

        $stmt = $pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        if ($stmt->fetchColumn()) {
            $errors[] = 'A harvest with the same date and quantity is already recorded for this planting.';
        }
    }

    return ['errors' => $errors, 'values' => $values, 'clean' => $clean];
}
