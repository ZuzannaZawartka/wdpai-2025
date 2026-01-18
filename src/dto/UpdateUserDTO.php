<?php

/**
 * Update user data transfer object
 * Holds validated user profile update data
 */
class UpdateUserDTO
{
    public ?string $firstName = null;
    public ?string $lastName = null;
    public ?string $email = null;
    public ?string $birthDate = null;
    public ?string $newPassword = null;

    public function __construct(array $data = [])
    {
        if (isset($data['firstName'])) $this->firstName = (string)$data['firstName'];
        if (isset($data['lastName'])) $this->lastName = (string)$data['lastName'];
        if (isset($data['email'])) $this->email = (string)$data['email'];
        if (isset($data['birthDate'])) $this->birthDate = $data['birthDate'];
        if (isset($data['newPassword'])) $this->newPassword = $data['newPassword'];
    }

    public function toArray(): array
    {
        $out = [];
        if ($this->firstName !== null) $out['firstName'] = $this->firstName;
        if ($this->lastName !== null) $out['lastName'] = $this->lastName;
        if ($this->email !== null) $out['email'] = $this->email;
        if ($this->birthDate !== null) $out['birthDate'] = $this->birthDate;
        if ($this->newPassword !== null) $out['newPassword'] = $this->newPassword;
        return $out;
    }
}
