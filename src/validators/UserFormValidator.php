<?php

class UserFormValidator {
    public static function validate(array $postData, string $currentHash = null, bool $isOwnProfile = false): array {
        $errors = [];
        
        $data = [
            'firstName' => trim($postData['firstName'] ?? ''),
            'lastName'  => trim($postData['lastName'] ?? ''),
            'email'     => trim($postData['email'] ?? ''),
            'birthDate' => trim($postData['birthDate'] ?? ''),
            'newPassword' => $postData['newPassword'] ?? ''
        ];

        if (mb_strlen($data['firstName']) < 2) $errors[] = "Imię jest wymagane (min. 2 znaki).";
        if (mb_strlen($data['lastName']) < 2)  $errors[] = "Nazwisko jest wymagane (min. 2 znaki).";
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = "Niepoprawny format adresu email.";

        if (!empty($data['birthDate'])) {
            $date = DateTime::createFromFormat('Y-m-d', $data['birthDate']);
            if (!$date || $date > new DateTime()) $errors[] = "Data urodzenia musi być poprawną datą z przeszłości.";
        }

        $confirm = $postData['confirmPassword'] ?? '';
        $old     = $postData['oldPassword'] ?? '';

        if (!empty($data['newPassword'])) {
            if (strlen($data['newPassword']) < 8) {
                $errors[] = "Nowe hasło musi mieć co najmniej 8 znaków.";
            }
            if ($data['newPassword'] !== $confirm) {
                $errors[] = "Hasła nie są identyczne.";
            }
            if ($isOwnProfile && $currentHash) {
                if (empty($old) || !password_verify($old, $currentHash)) {
                    $errors[] = "Obecne hasło jest niepoprawne.";
                }
            }
        } else {
            // Jeśli podano current password lub confirm password, ale nie podano new password
            if (($isOwnProfile && !empty($old)) || !empty($confirm)) {
                $errors[] = "Aby zmienić hasło, podaj nowe hasło i powtórz je.";
            }
        }

        return ['errors' => $errors, 'data' => $data];
    }
}