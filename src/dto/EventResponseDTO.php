<?php

require_once __DIR__ . '/../entity/Event.php';
require_once __DIR__ . '/../config/AppConfig.php';

/**
 * Event response data transfer object
 * Formats event data for API JSON responses
 */
class EventResponseDTO implements JsonSerializable
{
    private int $id;
    private string $title;
    private string $datetime;
    private ?string $description;
    private string $playersText;
    private string $level;
    private string $levelColor;
    private ?string $imageUrl;
    private bool $isPast;
    private string $sportName;
    private string $sportIcon;

    public function __construct(
        int $id,
        string $title,
        string $datetime,
        ?string $description,
        string $playersText,
        string $level,
        string $levelColor,
        ?string $imageUrl,
        bool $isPast,
        string $sportName,
        string $sportIcon
    ) {
        $this->id = $id;
        $this->title = $title;
        $this->datetime = $datetime;
        $this->description = $description;
        $this->playersText = $playersText;
        $this->level = $level;
        $this->levelColor = $levelColor;
        $this->imageUrl = $imageUrl;
        $this->isPast = $isPast;
        $this->sportName = $sportName;
        $this->sportIcon = $sportIcon;
    }

    public static function fromEntity(\Event $ev): self
    {
        $current = $ev->getCurrentPlayers();
        $max = $ev->getMaxPlayers();
        $min = $ev->getMinNeeded();

        $playersText = ($max === null) ? ($current . ' joined') : ($current . ' / ' . $max . ' joined');
        if ($min && $min > 0) {
            if ($max && $max > 0) {
                if ($min === $max) {
                    $playersText .= ' · Players ' . $max;
                } else {
                    $playersText .= ' · Range ' . $min . '–' . $max;
                }
            } else {
                $playersText .= ' · Minimum ' . $min;
            }
        } elseif ($max && $max > 0) {
            $playersText .= ' · Players ' . $max;
        }

        $isPast = false;
        if ($ev->getStartTime()) {
            $ts = strtotime($ev->getStartTime());
            $isPast = $ts ? ($ts < time()) : false;
        }

        return new self(
            (int)$ev->getId(),
            (string)$ev->getTitle(),
            $ev->getStartTime() ? (new DateTime($ev->getStartTime()))->format('D, M j, g:i A') : 'TBD',
            $ev->getDescription(),
            $playersText,
            $ev->getLevelName(),
            $ev->getLevelColor(),
            $ev->getImageUrl(),
            $isPast,
            $ev->getSportName() ?? '',
            $ev->getSportIcon()
        );
    }

    public function jsonSerialize(): mixed
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'datetime' => $this->datetime,
            'desc' => $this->description,
            'players' => $this->playersText,
            'level' => $this->level,
            'levelColor' => $this->levelColor,
            'imageUrl' => $this->imageUrl,
            'isPast' => $this->isPast,
            'sportName' => $this->sportName,
            'sportIcon' => $this->sportIcon
        ];
    }
}
