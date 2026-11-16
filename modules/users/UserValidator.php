<?php
class UserValidator {
    // Validate the "add user" form.
    // $roles is [role_id => role_name] (from User::fetchRoles()).
    // Returns ['errors' => [...], 'values' => as typed, 'clean' => what to save]
    public function validate(array $input, array $roles): array {
        $values = [
            'full_name' => trim((string) ($input['full_name'] ?? '')),
            'username'  => trim((string) ($input['username'] ?? '')),
            'role_id'   => trim((string) ($input['role_id'] ?? '')),
            'is_active' => isset($input['is_active']) ? '1' : '',
        ];
        $password = (string) ($input['password'] ?? '');
        $confirm  = (string) ($input['confirm_password'] ?? '');
        $errors   = [];

        if ($values['full_name'] === '' || mb_strlen($values['full_name']) > 100) {
            $errors[] = 'Full name is required (maximum 100 characters).';
        }
        if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $values['username'])) {
            $errors[] = 'Username must be 3 to 50 characters: letters, numbers, dot, dash or underscore.';
        }
        $roleId = filter_var($values['role_id'], FILTER_VALIDATE_INT);
        if ($roleId === false || !isset($roles[$roleId])) {
            $errors[] = 'Please choose a role.';
        }
        if (strlen($password) < 8 || strlen($password) > 72) {
            $errors[] = 'Password must be between 8 and 72 characters.';
        }
        if ($password !== $confirm) {
            $errors[] = 'The passwords do not match.';
        }

        return [
            'errors' => $errors,
            'values' => $values,
            'clean'  => [
                'full_name' => $values['full_name'],
                'username'  => $values['username'],
                'role_id'   => (int) $roleId,
                'is_active' => $values['is_active'] === '1',
                'password'  => $password,
            ],
        ];
    }
}