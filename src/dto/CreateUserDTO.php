<?php

/**
 * Create user data transfer object
 * Holds new user registration data
 */
class CreateUserDTO
{
    public string $email;
    public string $password;
    public string $firstname;
    public string $lastname;
    public ?string $birthDate = null;
    public ?float $latitude = null;
    public ?float $longitude = null;
    public string $role = 'user';
    public ?string $avatarUrl = null;

    public function __construct(array $data)
    {
        $this->email = (string)($data['email'] ?? '');
        $this->password = (string)($data['password'] ?? '');
        $this->firstname = (string)($data['firstname'] ?? '');
        $this->lastname = (string)($data['lastname'] ?? '');
        $this->birthDate = $data['birth_date'] ?? null;
        $this->latitude = isset($data['latitude']) ? (float)$data['latitude'] : null;
        $this->longitude = isset($data['longitude']) ? (float)$data['longitude'] : null;
        $this->role = $data['role'] ?? 'user';
        $this->avatarUrl = $data['avatar_url'] ?? null;
    }
}
