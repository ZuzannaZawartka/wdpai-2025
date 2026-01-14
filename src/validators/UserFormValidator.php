<?php

class UserFormValidator {
    public static function validate(array $postData): array {
        $errors = [];
        $firstName = trim($postData['firstName'] ?? '');
        $lastName = trim($postData['lastName'] ?? '');
        $email = trim($postData['email'] ?? '');
        $birthDate = trim($postData['birthDate'] ?? '');
        $newPassword = trim($postData['newPassword'] ?? '');
        $confirmPassword = trim($postData['confirmPassword'] ?? '');

        if (empty($firstName) || mb_strlen($firstName, 'UTF-8') < 2) {
            $errors[] = 'First name is required and must be at least 2 characters.';
        }
        if (empty($lastName) || mb_strlen($lastName, 'UTF-8') < 2) {
            $errors[] = 'Last name is required and must be at least 2 characters.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Valid email is required.';
        }
        if (!empty($birthDate)) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthDate)) {
                $errors[] = 'Birth date must be in YYYY-MM-DD format.';
            } else {
                $today = date('Y-m-d');
                if ($birthDate > $today) {
                    $errors[] = 'Birth date cannot be in the future.';
                }
            }
        }
        if (!empty($newPassword) || !empty($confirmPassword)) {
            if (strlen($newPassword) < 8) {
                $errors[] = 'New password must be at least 8 characters.';
            }
            if ($newPassword !== $confirmPassword) {
                $errors[] = 'Passwords do not match.';
            }
        }
        return [
            'errors' => $errors,
            'data' => [
                'firstName' => $firstName,
                'lastName' => $lastName,
                'email' => $email,
                'birthDate' => $birthDate,
                'newPassword' => $newPassword,
            ]
        ];
    }
}
