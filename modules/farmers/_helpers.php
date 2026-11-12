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

// Checks the farmer form. Returns:
//   errors - a list of messages to show the user
//   values - what to show again in the form (text, as typed)
//   clean  - what to save in the database
function validate_farmer(array $input, PDO $pdo, ?int $ignoreId = null): array
{
    $errors = [];

    $values = [
        'first_name'      => trim((string) ($input['first_name'] ?? '')),
        'last_name'       => trim((string) ($input['last_name'] ?? '')),
        'contact_number'  => trim((string) ($input['contact_number'] ?? '')),
        'address'         => trim((string) ($input['address'] ?? '')),
        'date_registered' => trim((string) ($input['date_registered'] ?? '')),
    ];

    $clean = $values;

    foreach (['first_name' => 'First name', 'last_name' => 'Last name'] as $field => $label) {
        if ($values[$field] === '') {
            $errors[] = $label . ' is required.';
        } elseif (mb_strlen($values[$field]) > 50) {
            $errors[] = $label . ' must be 50 characters or fewer.';
        } elseif (!preg_match('/^\p{L}[\p{L}\s.\'-]*$/u', $values[$field])) {
            $errors[] = $label . ' may contain only letters, spaces, hyphens, apostrophes and periods.';
        }
    }

    // Spaces, dashes and brackets are removed so the same number is always saved the same way
    $contact = preg_replace('/[\s\-()]+/', '', $values['contact_number']);
    $clean['contact_number'] = $contact;
    if ($values['contact_number'] === '') {
        $errors[] = 'Contact number is required.';
    } elseif (!preg_match('/^\+?[0-9]{7,15}$/', $contact)) {
        $errors[] = 'Enter a valid contact number (7 to 15 digits, with an optional + at the start).';
    }

    if ($values['address'] === '') {
        $errors[] = 'Address is required.';
    } elseif (mb_strlen($values['address']) > 255) {
        $errors[] = 'Address must be 255 characters or fewer.';
    }

    if ($values['date_registered'] === '') {
        $errors[] = 'Date registered is required.';
    } elseif (!valid_date_string($values['date_registered'])) {
        $errors[] = 'Date registered is not a valid date.';
    } elseif ($values['date_registered'] > today_manila()) {
        $errors[] = 'Date registered cannot be in the future.';
    }

    // The same contact number must not belong to two farmers
    if (!$errors) {
        $sql    = 'SELECT 1 FROM farmer WHERE contact_number = :contact';
        $params = [':contact' => $clean['contact_number']];

        if ($ignoreId !== null) {
            $sql .= ' AND farmer_id <> :id';
            $params[':id'] = $ignoreId;
        }

        $stmt = $pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        if ($stmt->fetchColumn()) {
            $errors[] = 'Another farmer already uses this contact number.';
        }
    }

    return ['errors' => $errors, 'values' => $values, 'clean' => $clean];
}
