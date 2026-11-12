<?php
require_once __DIR__ . '/../../includes/functions.php';

// Dropdown list of farmers: [farmer_id => "Last, First"]
function fetch_farmer_options(PDO $pdo): array
{
    $rows = $pdo->query(
        "SELECT farmer_id, last_name || ', ' || first_name
         FROM farmer ORDER BY last_name, first_name"
    )->fetchAll(PDO::FETCH_NUM);

    $options = [];
    foreach ($rows as [$id, $name]) {
        $options[(int) $id] = $name;
    }
    return $options;
}

// Checks the farm form. Returns:
//   errors - a list of messages to show the user
//   values - what to show again in the form (text, as typed)
//   clean  - what to save in the database
function validate_farm(
    array $input,
    array $farmers,
    array $soilTypes,
    array $irrigationTypes,
    PDO $pdo,
    ?int $ignoreId = null
): array {
    $errors = [];

    $values = [
        'farmer_id'     => trim((string) ($input['farmer_id'] ?? '')),
        'farm_name'     => trim((string) ($input['farm_name'] ?? '')),
        'location'      => trim((string) ($input['location'] ?? '')),
        'area_hectares' => trim((string) ($input['area_hectares'] ?? '')),
        'soil_type_id'  => trim((string) ($input['soil_type_id'] ?? '')),
        'irrigation_id' => trim((string) ($input['irrigation_id'] ?? '')),
    ];

    $clean = [
        'farmer_id'     => null,
        'farm_name'     => $values['farm_name'],
        'location'      => $values['location'],
        'area_hectares' => null,
        'soil_type_id'  => null,
        'irrigation_id' => null,
    ];

    // Dropdowns: the chosen ID must really exist in its table
    $dropdowns = [
        ['farmer_id',     $farmers,         'an owner (farmer)'],
        ['soil_type_id',  $soilTypes,       'a soil type'],
        ['irrigation_id', $irrigationTypes, 'an irrigation type'],
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

    if ($values['farm_name'] === '') {
        $errors[] = 'Farm name is required.';
    } elseif (mb_strlen($values['farm_name']) > 100) {
        $errors[] = 'Farm name must be 100 characters or fewer.';
    }

    if ($values['location'] === '') {
        $errors[] = 'Location is required.';
    } elseif (mb_strlen($values['location']) > 255) {
        $errors[] = 'Location must be 255 characters or fewer.';
    }

    if ($values['area_hectares'] === '') {
        $errors[] = 'Area (hectares) is required.';
    } elseif (!preg_match('/^\d+(\.\d{1,2})?$/', $values['area_hectares'])
        || (float) $values['area_hectares'] <= 0
        || (float) $values['area_hectares'] > 100000) {
        $errors[] = 'Area must be a number above 0 and up to 100000, with at most 2 decimal places.';
    } else {
        $clean['area_hectares'] = $values['area_hectares'];
    }

    // The same owner cannot have two farms with the same name
    if (!$errors) {
        $sql    = 'SELECT 1 FROM farm WHERE farmer_id = :farmer_id AND LOWER(farm_name) = LOWER(:name)';
        $params = [':farmer_id' => $clean['farmer_id'], ':name' => $clean['farm_name']];

        if ($ignoreId !== null) {
            $sql .= ' AND farm_id <> :id';
            $params[':id'] = $ignoreId;
        }

        $stmt = $pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        if ($stmt->fetchColumn()) {
            $errors[] = 'This farmer already has a farm with that name.';
        }
    }

    return ['errors' => $errors, 'values' => $values, 'clean' => $clean];
}
