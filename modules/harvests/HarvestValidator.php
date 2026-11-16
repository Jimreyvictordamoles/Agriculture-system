<?php
require_once __DIR__ . '/Harvest.php';

class HarvestValidator {
    private Harvest $harvests;

    public function __construct(Harvest $harvests) {
        $this->harvests = $harvests;
    }

    // Returns ['errors' => [...], 'values' => as typed, 'clean' => what to save]
    public function validate(array $input, array $plantings, array $grades, array $remarks, ?int $ignoreId = null): array {
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
        if (!$errors && $this->harvests->existsDuplicate(
            $clean['planting_id'], $clean['harvest_date'], $clean['quantity_kg'], $ignoreId
        )) {
            $errors[] = 'A harvest with the same date and quantity is already recorded for this planting.';
        }

        return ['errors' => $errors, 'values' => $values, 'clean' => $clean];
    }
}