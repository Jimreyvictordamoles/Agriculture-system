<?php
require_once __DIR__ . '/../../includes/functions.php';

// Checks the crop form. Returns:
//   errors - a list of messages to show the user
//   values - what to show again in the form (text, as typed)
//   clean  - what to save in the database (null for empty fields)
function validate_crop(array $input, array $categories, array $seasons, PDO $pdo, ?int $ignoreId = null): array
{
    $errors = [];

    $values = [
        'crop_name'           => trim((string) ($input['crop_name'] ?? '')),
        'variety'             => trim((string) ($input['variety'] ?? '')),
        'category_id'         => trim((string) ($input['category_id'] ?? '')),
        'season_id'           => trim((string) ($input['season_id'] ?? '')),
        'growing_period_days' => trim((string) ($input['growing_period_days'] ?? '')),
    ];

    $clean = [
        'crop_name'           => $values['crop_name'],
        'variety'             => $values['variety'] === '' ? null : $values['variety'],
        'category_id'         => null,
        'season_id'           => null,
        'growing_period_days' => null,
    ];

    if ($values['crop_name'] === '') {
        $errors[] = 'Crop name is required.';
    } elseif (mb_strlen($values['crop_name']) > 100) {
        $errors[] = 'Crop name must be 100 characters or fewer.';
    }

    if (mb_strlen($values['variety']) > 100) {
        $errors[] = 'Variety must be 100 characters or fewer.';
    }

    if ($values['category_id'] === '') {
        $errors[] = 'Please choose a category.';
    } 
    elseif (ctype_digit($values['category_id']) && isset($categories[(int) $values['category_id']])) {
        $clean['category_id'] = (int) $values['category_id'];
    } 
    else {
        $errors[] = 'Please choose a valid category.';
    }

    if ($values['season_id'] === '') {
        $errors[] = 'Please choose a season.';
    } 
    elseif (ctype_digit($values['season_id']) && isset($seasons[(int) $values['season_id']])) {
        $clean['season_id'] = (int) $values['season_id'];
    } 
    else {
        $errors[] = 'Please choose a valid season.';
    }


    if ($values['growing_period_days'] !== '') {
        $days = $values['growing_period_days'];
        if (!ctype_digit($days) || (int) $days < 1 || (int) $days > 3650) {
            $errors[] = 'Growing period must be a whole number of days between 1 and 3650.';
        } else {
            $clean['growing_period_days'] = (int) $days;
        }
    }

    // Same crop name + variety must not exist twice (ignoring capital letters)
    if (!$errors) {
        $sql    = 'SELECT 1 FROM crop
                   WHERE LOWER(crop_name) = LOWER(:name)
                     AND LOWER(variety) IS NOT DISTINCT FROM LOWER(:variety)';
        $params = [':name' => $clean['crop_name'], ':variety' => $clean['variety']];

        if ($ignoreId !== null) {
            $sql .= ' AND crop_id <> :id';
            $params[':id'] = $ignoreId;
        }

        $stmt = $pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        if ($stmt->fetchColumn()) {
            $errors[] = 'A crop with the same name and variety already exists.';
        }
    }

    return ['errors' => $errors, 'values' => $values, 'clean' => $clean];
}
