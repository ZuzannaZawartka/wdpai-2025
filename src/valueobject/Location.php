<?php

class Location
{
    private float $lat;
    private float $lng;

    public function __construct(float $lat, float $lng)
    {
        if ($lat < -90 || $lat > 90) throw new InvalidArgumentException('Latitude out of range');
        if ($lng < -180 || $lng > 180) throw new InvalidArgumentException('Longitude out of range');
        $this->lat = $lat;
        $this->lng = $lng;
    }

    public function lat(): float { return $this->lat; }
    public function lng(): float { return $this->lng; }

    public function toArray(): array { return ['lat' => $this->lat, 'lng' => $this->lng]; }
}
