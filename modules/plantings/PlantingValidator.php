<?php
require_once __DIR__ . '/Planting.php';

class PlantingValidator {
    private Planting $plantings;

    public function __construct(Planting $plantings) {
        $this->plantings = $plantings;
    }

    // Returns ['errors' => [...], 'values' => as typed, 'clean' => what to save]
    public function validate(array $input, array $farms, array $crops, array $statuses, ?int $ignoreId = null): array {
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
        if (!$errors && $this->plantings->existsDuplicate(
            $clean['farm_id'], $clean['crop_id'], $clean['planting_date'], $ignoreId
        )) {
            $errors[] = 'This crop is already recorded on this farm for the same planting date.';
        }

        return ['errors' => $errors, 'values' => $values, 'clean' => $clean];
    }
}