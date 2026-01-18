<?php

require_once __DIR__ . '/../dto/UpdateUserDTO.php';

/**
 * User form validator
 * Validates user profile update forms with password validation
 */
class UserFormValidator
{
    /**
     * Validates user profile form data
     * 
     * @param array $postData Form POST data
     * @param string|null $currentHash Current password hash (for password changes)
     * @param bool $isOwnProfile Whether user is editing own profile
     * @return array Validation result with errors, data, and DTO
     */
    public static function validate(array $postData, ?string $currentHash = null, bool $isOwnProfile = false): array
    {
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

            if (($isOwnProfile && !empty($old)) || !empty($confirm)) {
                $errors[] = "Aby zmienić hasło, podaj nowe hasło i powtórz je.";
            }
        }
        return [
            'errors' => $errors,
            'data' => $data,
            'dto' => new UpdateUserDTO($data)
        ];
    }
}
