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
        $oldPassword = trim($postData['oldPassword'] ?? '');

        if (empty($firstName) || mb_strlen($firstName, 'UTF-8') < 2) {
            $errors[] = 'First name is required and must be at least 2 characters.';
        }
        if (empty($lastName) || mb_strlen($lastName, 'UTF-8') < 2) {
            $errors[] = 'Last name is required and must be at least 2 characters.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Valid email is required.';
        }
        if ($birthDate !== '') {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthDate)) {
                $errors[] = 'Birth date must be in YYYY-MM-DD format.';
            } else {
                $birthTimestamp = strtotime($birthDate);
                $todayTimestamp = strtotime(date('Y-m-d'));
                if ($birthTimestamp === false) {
                    $errors[] = 'Invalid birth date.';
                } elseif ($birthTimestamp >= $todayTimestamp) {
                    $errors[] = 'Birth date must be before today.';
                }
            }
        }
        if (!empty($newPassword) || !empty($confirmPassword) || !empty($oldPassword)) {
            if (empty($newPassword)) {
                $errors[] = 'New password is required.';
            }
            if (empty($confirmPassword)) {
                $errors[] = 'Password confirmation is required.';
            }
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
