<?php

/**
 * Event metadata value object
 * Immutable event title and description with validation
 */
class EventMetadata
{
    private string $title;
    private ?string $description;

    public function __construct(string $title, ?string $description)
    {
        $title = trim($title);
        if ($title === '' || strlen($title) > 200) {
            throw new InvalidArgumentException('Invalid title');
        }
        $this->title = $title;
        $this->description = $description !== null ? trim($description) : null;
    }

    public function title(): string
    {
        return $this->title;
    }
    public function description(): ?string
    {
        return $this->description;
    }

    public function short(int $len = 50): string
    {
        $d = $this->description ?? '';
        return strlen($d) <= $len ? $d : substr($d, 0, $len) . '...';
    }
}
