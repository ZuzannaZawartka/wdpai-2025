<?php

/**
 * Update event data transfer object
 * Holds validated event update data
 */
class UpdateEventDTO
{
    public ?string $title = null;
    public ?string $description = null;
    public ?int $sportId = null;
    public ?string $locationText = null;
    public ?float $latitude = null;
    public ?float $longitude = null;
    public ?string $startTime = null;
    public ?int $levelId = null;
    public ?string $imageUrl = null;
    public ?int $maxPlayers = null;
    public ?int $minNeeded = null;

    public function __construct(array $data = [])
    {
        if (isset($data['title'])) $this->title = (string)$data['title'];
        if (isset($data['description'])) $this->description = $data['description'];
        if (isset($data['sport_id'])) $this->sportId = (int)$data['sport_id'];
        if (isset($data['location_text'])) $this->locationText = $data['location_text'];
        if (isset($data['latitude'])) $this->latitude = (float)$data['latitude'];
        if (isset($data['longitude'])) $this->longitude = (float)$data['longitude'];
        if (isset($data['start_time'])) $this->startTime = $data['start_time'];
        if (isset($data['level_id'])) $this->levelId = (int)$data['level_id'];
        if (isset($data['image_url'])) $this->imageUrl = $data['image_url'];
        if (isset($data['max_players'])) $this->maxPlayers = (int)$data['max_players'];
        if (isset($data['min_needed'])) $this->minNeeded = (int)$data['min_needed'];
    }

    public function toArray(): array
    {
        $out = [];
        if ($this->title !== null) $out['title'] = $this->title;
        if ($this->description !== null) $out['description'] = $this->description;
        if ($this->sportId !== null) $out['sport_id'] = $this->sportId;
        if ($this->locationText !== null) $out['location_text'] = $this->locationText;
        if ($this->latitude !== null) $out['latitude'] = $this->latitude;
        if ($this->longitude !== null) $out['longitude'] = $this->longitude;
        if ($this->startTime !== null) $out['start_time'] = $this->startTime;
        if ($this->levelId !== null) $out['level_id'] = $this->levelId;
        if ($this->imageUrl !== null) $out['image_url'] = $this->imageUrl;
        if ($this->maxPlayers !== null) $out['max_players'] = $this->maxPlayers;
        if ($this->minNeeded !== null) $out['min_needed'] = $this->minNeeded;
        return $out;
    }
}
