<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/Farm.php';

class FarmValidator {
    private Farm $farm;

    public function __construct(Farm $farm) {
        $this->farm = $farm;
    }

    /**
     * @return array{errors: string[], values: array, clean: array}
     */
    public function validate(
        array $input,
        array $farmers,
        array $soilTypes,
        array $irrigationTypes,
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
        if (!$errors && $this->farm->existsByOwnerAndName($clean['farmer_id'], $clean['farm_name'], $ignoreId)) {
            $errors[] = 'This farmer already has a farm with that name.';
        }

        return ['errors' => $errors, 'values' => $values, 'clean' => $clean];
    }
}