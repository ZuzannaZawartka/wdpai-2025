<?php

class Sport
{
    private ?int $id;
    private ?string $name;
    private ?string $icon;

    public function __construct(array $data)
    {
        $this->id = isset($data['id']) ? (int)$data['id'] : null;
        $this->name = isset($data['name']) ? (string)$data['name'] : null;
        $this->icon = isset($data['icon']) ? (string)$data['icon'] : null;
    }

    public function getId(): ?int
    {
        return $this->id;
    }
    public function getName(): ?string
    {
        return $this->name;
    }
    public function getIcon(): string
    {
        require_once __DIR__ . '/../config/AppConfig.php';
        return $this->icon ?: AppConfig::DEFAULT_SPORT_ICON;
    }
}
