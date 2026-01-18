<?php

/**
 * User entity class
 * Represents a user with personal information and location
 */
class User
{
    private ?int $id;
    private ?string $firstname;
    private ?string $lastname;
    private ?string $email;
    private ?string $role;
    private ?string $birthDate;
    private ?float $latitude;
    private ?float $longitude;
    private ?string $avatarUrl;

    public function __construct(array $data)
    {
        $this->id = isset($data['id']) ? (int)$data['id'] : null;
        $this->firstname = isset($data['firstname']) ? (string)$data['firstname'] : null;
        $this->lastname = isset($data['lastname']) ? (string)$data['lastname'] : null;
        $this->email = isset($data['email']) ? (string)$data['email'] : null;
        $this->role = isset($data['role']) ? (string)$data['role'] : null;
        $this->birthDate = isset($data['birth_date']) ? (string)$data['birth_date'] : null;
        $this->latitude = isset($data['latitude']) ? (float)$data['latitude'] : null;
        $this->longitude = isset($data['longitude']) ? (float)$data['longitude'] : null;
        $this->avatarUrl = isset($data['avatar_url']) ? (string)$data['avatar_url'] : null;
    }

    public function getId(): ?int
    {
        return $this->id;
    }
    public function getFirstname(): ?string
    {
        return $this->firstname;
    }
    public function getLastname(): ?string
    {
        return $this->lastname;
    }
    public function getEmail(): ?string
    {
        return $this->email;
    }
    public function getRole(): ?string
    {
        return $this->role;
    }
    public function getBirthDate(): ?string
    {
        return $this->birthDate;
    }
    public function getLatitude(): ?float
    {
        return $this->latitude;
    }
    public function getLongitude(): ?float
    {
        return $this->longitude;
    }
    public function getAvatarUrl(): string
    {
        require_once __DIR__ . '/../config/AppConfig.php';
        return $this->avatarUrl ?: AppConfig::DEFAULT_USER_AVATAR;
    }
}
