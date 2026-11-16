<?php
require_once __DIR__ . '/Farmer.php';

class FarmerValidator {
    private const NAME_MAX    = 50;
    private const ADDRESS_MAX = 255;
    private const FORBIDDEN   = '/[!@#$%^&*]/';

    private Farmer $farmer;

    public function __construct(Farmer $farmer) {
        $this->farmer = $farmer;
    }

    /**
     * @param array    $input    Usually $_POST
     * @param int|null $ignoreId Farmer ID to exclude from the duplicate contact check (edit)
     * @return array{errors: string[], values: array, clean: array}
     */
    public function validate(array $input, ?int $ignoreId = null): array {
        $errors = [];

        // Raw values, kept so the form can be re-displayed on error
        $values = [
            'first_name'      => trim((string) ($input['first_name'] ?? '')),
            'last_name'       => trim((string) ($input['last_name'] ?? '')),
            'contact_number'  => trim((string) ($input['contact_number'] ?? '')),
            'address'         => trim((string) ($input['address'] ?? '')),
            'date_registered' => trim((string) ($input['date_registered'] ?? '')),
        ];

        // Names
        $this->validateName($values['first_name'], 'First name', $errors);
        $this->validateName($values['last_name'], 'Last name', $errors);

        // Contact number (assumption: 7-15 digits, optional + - ( ) and spaces)
        $contact = $values['contact_number'];
        if ($contact === '') {
            $errors[] = 'Contact number is required.';
        } elseif (!preg_match('/^\+?[0-9\s\-()]+$/', $contact)
            || strlen(preg_replace('/\D/', '', $contact)) < 7
            || strlen(preg_replace('/\D/', '', $contact)) > 15) {
            $errors[] = 'Contact number is not valid.';
        } elseif ($this->farmer->existsByContact($contact, $ignoreId)) {
            $errors[] = 'Another farmer already uses this contact number.';
        }

        // Address
        if ($values['address'] === '') {
            $errors[] = 'Address is required.';
        } elseif (mb_strlen($values['address']) > self::ADDRESS_MAX) {
            $errors[] = 'Address must be at most ' . self::ADDRESS_MAX . ' characters.';
        }

        // Date registered
        $date = DateTime::createFromFormat('!Y-m-d', $values['date_registered']);
        if ($values['date_registered'] === '') {
            $errors[] = 'Date registered is required.';
        } elseif (!$date || $date->format('Y-m-d') !== $values['date_registered']) {
            $errors[] = 'Date registered is not a valid date.';
        } elseif ($values['date_registered'] > today_manila()) {
            $errors[] = 'Date registered cannot be in the future.';
        }

        return [
            'errors' => $errors,
            'values' => $values,
            'clean'  => $values,
        ];
    }

    private function validateName(string $value, string $label, array &$errors): void {
        if ($value === '') {
            $errors[] = $label . ' is required.';
        } elseif (mb_strlen($value) > self::NAME_MAX) {
            $errors[] = $label . ' must be at most ' . self::NAME_MAX . ' characters.';
        } elseif (preg_match(self::FORBIDDEN, $value) === 1) {
            $errors[] = $label . ' cannot contain these characters: ! @ # $ % ^ & *';
        }
    }
}