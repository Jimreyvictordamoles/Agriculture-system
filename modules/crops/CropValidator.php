<?php
require_once __DIR__ . '/Crop.php';

class CropValidator {
    private Crop $cropModel;

    public function __construct(Crop $cropModel) {
        $this->cropModel = $cropModel;
    }

    /**
     * @param array    $input      The submitted form data (usually $_POST)
     * @param array    $categories [category_id => name]
     * @param array    $seasons    [season_id => name]
     * @param int|null $ignoreId   The crop being edited, so it doesn't clash with itself
     *
     * @return array ['errors' => string[], 'values' => form values to show again, 'clean' => values safe to save]
     */
    public function validate(array $input, array $categories, array $seasons, ?int $ignoreId = null): array {
        $errors = [];

        // What the form shows again if something is wrong
        $values = [
            'crop_name'           => trim((string) ($input['crop_name'] ?? '')),
            'variety'             => trim((string) ($input['variety'] ?? '')),
            'category_id'         => trim((string) ($input['category_id'] ?? '')),
            'season_id'           => trim((string) ($input['season_id'] ?? '')),
            'growing_period_days' => trim((string) ($input['growing_period_days'] ?? '')),
        ];

        // What gets saved to the database
        $clean = [
            'crop_name'           => $values['crop_name'],
            'variety'             => $values['variety'] === '' ? null : $values['variety'],
            'category_id'         => null,
            'season_id'           => null,
            'growing_period_days' => null,
        ];

        // Crop name (required)
        if ($values['crop_name'] === '') {
            $errors[] = 'Crop name is required.';
        } elseif (mb_strlen($values['crop_name']) > 100) {
            $errors[] = 'Crop name must be 100 characters or fewer.';
        }

        // Variety (optional)
        if ($clean['variety'] !== null && mb_strlen($clean['variety']) > 100) {
            $errors[] = 'Variety must be 100 characters or fewer.';
        }

        // Category (optional, but must exist when chosen)
        if ($values['category_id'] !== '') {
            if (!ctype_digit($values['category_id']) || !isset($categories[(int) $values['category_id']])) {
                $errors[] = 'Please choose a valid category.';
            } else {
                $clean['category_id'] = (int) $values['category_id'];
            }
        }

        // Season (optional, but must exist when chosen)
        if ($values['season_id'] !== '') {
            if (!ctype_digit($values['season_id']) || !isset($seasons[(int) $values['season_id']])) {
                $errors[] = 'Please choose a valid season.';
            } else {
                $clean['season_id'] = (int) $values['season_id'];
            }
        }

        // Growing period in days (optional, whole number from 1 to 1000)
        if ($values['growing_period_days'] !== '') {
            if (!ctype_digit($values['growing_period_days'])
                || (int) $values['growing_period_days'] < 1
                || (int) $values['growing_period_days'] > 1000) {
                $errors[] = 'Growing period must be a whole number of days from 1 to 1000.';
            } else {
                $clean['growing_period_days'] = (int) $values['growing_period_days'];
            }
        }

        // Duplicate check (only when the other fields are fine)
        if (!$errors && $this->cropModel->existsByNameVariety($clean['crop_name'], $clean['variety'], $ignoreId)) {
            $errors[] = 'A crop with the same name and variety already exists.';
        }

        return ['errors' => $errors, 'values' => $values, 'clean' => $clean];
    }
}