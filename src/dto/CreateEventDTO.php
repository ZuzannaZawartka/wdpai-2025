<?php

/**
 * Create event data transfer object
 * Holds new event creation data
 */
class CreateEventDTO
{
    public ?int $ownerId;
    public string $title;
    public ?string $description;
    public ?int $sportId;
    public ?string $locationText;
    public ?float $latitude;
    public ?float $longitude;
    public ?string $startTime;
    public ?int $levelId;
    public ?string $imageUrl;
    public ?int $maxPlayers;
    public ?int $minNeeded;

    public function __construct(array $data)
    {
        $this->ownerId = $data['owner_id'] ?? null;
        $this->title = (string)($data['title'] ?? '');
        $this->description = $data['description'] ?? null;
        $this->sportId = $data['sport_id'] ?? null;
        $this->locationText = $data['location_text'] ?? null;
        $this->latitude = isset($data['latitude']) ? (float)$data['latitude'] : null;
        $this->longitude = isset($data['longitude']) ? (float)$data['longitude'] : null;
        $this->startTime = $data['start_time'] ?? null;
        $this->levelId = $data['level_id'] ?? null;
        $this->imageUrl = $data['image_url'] ?? null;
        $this->maxPlayers = isset($data['max_players']) ? (int)$data['max_players'] : null;
        $this->minNeeded = isset($data['min_needed']) ? (int)$data['min_needed'] : null;
    }

    public function toArray(): array
    {
        return [
            'owner_id' => $this->ownerId,
            'title' => $this->title,
            'description' => $this->description,
            'sport_id' => $this->sportId,
            'location_text' => $this->locationText,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'start_time' => $this->startTime,
            'level_id' => $this->levelId,
            'image_url' => $this->imageUrl,
            'max_players' => $this->maxPlayers,
            'min_needed' => $this->minNeeded,
        ];
    }
}
