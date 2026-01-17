<?php

require_once __DIR__ . '/../valueobject/Location.php';

class EventSearchRequestDTO
{
    public array $sports;
    public string $level;
    public ?string $locationString;
    public ?float $radius;
    public ?float $latitude;
    public ?float $longitude;

    public function __construct(
        array $sports = [],
        string $level = 'Any',
        ?string $locationString = null,
        ?float $radius = 10.0,
        ?float $latitude = null,
        ?float $longitude = null
    ) {
        $this->sports = $sports;
        $this->level = $level;
        $this->locationString = $locationString;
        $this->radius = $radius;
        $this->latitude = $latitude;
        $this->longitude = $longitude;
    }

    public static function fromRequest(array $request): self
    {
        $sports = isset($request['sports']) && is_array($request['sports']) ? $request['sports'] : [];
        $level = isset($request['level']) ? trim($request['level']) : 'Any';
        $locationString = isset($request['loc']) ? trim($request['loc']) : null;
        $radius = isset($request['radius']) ? (float)$request['radius'] : 10.0;

        $lat = null;
        $lng = null;

        if ($locationString && preg_match('/^\s*(-?\d+\.?\d*)\s*,\s*(-?\d+\.?\d*)\s*$/', $locationString, $m)) {
            $lat = (float)$m[1];
            $lng = (float)$m[2];
        }

        return new self($sports, $level, $locationString, $radius, $lat, $lng);
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }
}
