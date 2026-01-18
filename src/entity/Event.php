<?php

require_once __DIR__ . '/../config/AppConfig.php';

/**
 * Event entity class
 * Represents a sports event with location, participants, and owner information
 */
class Event
{
    private ?int $id;
    private string $title;
    private ?string $description;
    private ?int $sportId;
    private ?string $sportName;
    private ?string $sportIcon;
    private ?string $locationText;
    private ?float $latitude;
    private ?float $longitude;
    private ?string $startTime;
    private ?int $levelId;
    private ?string $levelName;
    private ?string $levelColor;
    private ?string $imageUrl;
    private ?int $maxPlayers;
    private ?int $minNeeded;
    private ?int $ownerId;
    private ?string $ownerEmail;
    private ?string $ownerFirstName;
    private ?string $ownerLastName;
    private ?string $ownerAvatarUrl;
    private int $currentPlayers;
    private array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
        $this->id = isset($data['id']) ? (int)$data['id'] : null;
        $this->title = (string)($data['title'] ?? '');
        $this->description = isset($data['description']) ? (string)$data['description'] : null;
        $this->sportId = isset($data['sport_id']) ? (int)$data['sport_id'] : null;
        $this->sportName = isset($data['sport_name']) ? (string)$data['sport_name'] : null;
        $this->sportIcon = isset($data['sport_icon']) ? (string)$data['sport_icon'] : null;
        $this->locationText = isset($data['location_text']) ? (string)$data['location_text'] : null;
        $this->latitude = isset($data['latitude']) ? (float)$data['latitude'] : null;
        $this->longitude = isset($data['longitude']) ? (float)$data['longitude'] : null;
        $this->startTime = isset($data['start_time']) ? (string)$data['start_time'] : null;
        $this->levelId = isset($data['level_id']) ? (int)$data['level_id'] : null;
        $this->levelName = isset($data['level_name']) ? (string)$data['level_name'] : null;
        $this->levelColor = isset($data['level_color']) ? (string)$data['level_color'] : null;
        $this->imageUrl = isset($data['image_url']) ? (string)$data['image_url'] : null;
        $this->maxPlayers = isset($data['max_players']) ? (is_numeric($data['max_players']) ? (int)$data['max_players'] : null) : null;
        $this->minNeeded = isset($data['min_needed']) ? (is_numeric($data['min_needed']) ? (int)$data['min_needed'] : null) : null;
        $this->ownerId = isset($data['owner_id']) ? (int)$data['owner_id'] : null;
        $this->ownerEmail = isset($data['owner_email']) ? (string)$data['owner_email'] : null;
        $this->ownerFirstName = isset($data['firstname']) ? (string)$data['firstname'] : null;
        $this->ownerLastName = isset($data['lastname']) ? (string)$data['lastname'] : null;
        $this->ownerAvatarUrl = isset($data['avatar_url']) ? (string)$data['avatar_url'] : null;
        $this->currentPlayers = isset($data['current_players']) ? (int)$data['current_players'] : 0;
    }

    public function getId(): ?int
    {
        return $this->id;
    }
    public function getTitle(): string
    {
        return $this->title;
    }
    public function getDescription(): ?string
    {
        return $this->description;
    }
    public function getSportId(): ?int
    {
        return $this->sportId;
    }
    public function getSportName(): ?string
    {
        return $this->sportName;
    }
    public function getSportIcon(): string
    {
        return $this->sportIcon ?: AppConfig::DEFAULT_SPORT_ICON;
    }
    public function getLocationText(): ?string
    {
        return $this->locationText;
    }
    public function getLatitude(): ?float
    {
        return $this->latitude;
    }
    public function getLongitude(): ?float
    {
        return $this->longitude;
    }
    public function getStartTime(): ?string
    {
        return $this->startTime;
    }
    public function getLevelId(): ?int
    {
        return $this->levelId;
    }
    public function getLevelName(): string
    {
        return $this->levelName ?: AppConfig::DEFAULT_LEVEL_NAME;
    }
    public function getLevelColor(): string
    {
        return $this->levelColor ?: AppConfig::DEFAULT_LEVEL_COLOR;
    }
    public function getImageUrl(): string
    {
        return $this->imageUrl ?: AppConfig::DEFAULT_EVENT_IMAGE;
    }
    public function getMaxPlayers(): ?int
    {
        return $this->maxPlayers;
    }
    public function getMinNeeded(): ?int
    {
        return $this->minNeeded;
    }
    public function getOwnerId(): ?int
    {
        return $this->ownerId;
    }
    public function getOwnerEmail(): ?string
    {
        return $this->ownerEmail;
    }
    public function getOwnerFirstName(): ?string
    {
        return $this->ownerFirstName;
    }
    public function getOwnerLastName(): ?string
    {
        return $this->ownerLastName;
    }
    public function getOwnerAvatarUrl(): string
    {
        return $this->ownerAvatarUrl ?: AppConfig::DEFAULT_USER_AVATAR;
    }
    public function getCurrentPlayers(): int
    {
        return $this->currentPlayers;
    }

    public function getRawData(): array
    {
        return $this->data;
    }
}
