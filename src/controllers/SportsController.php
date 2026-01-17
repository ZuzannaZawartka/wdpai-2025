<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repository/SportsRepository.php';
require_once __DIR__ . '/../repository/EventRepository.php';
require_once __DIR__ . '/../entity/Event.php';

class SportsController extends AppController
{
    private SportsRepository $sportsRepository;
    private EventRepository $eventRepository;

    public function __construct()
    {
        parent::__construct();
        $this->sportsRepository = new SportsRepository();
        $this->eventRepository = new EventRepository();
    }

    public function index(): void
    {
        $filters = $this->parseFilters();
        $rawEvents = $this->eventRepository->getFilteredEventsListing($filters, $this->isAdmin());

        $matches = [];
        foreach ($rawEvents as $row) {
            $ev = new \Event($row);

            // Filter full events for non-admins
            // Note: DB filtering is preferred, but getFilteredEventsListing might not filter full events?
            // Main's code did: $events = array_filter($events, fn($ev) => !$this->eventRepository->isEventFull(...));
            // We can check fullness on the entity since it has max and current players (from SQL view/subquery)
            $isFull = $ev->getMaxPlayers() > 0 && $ev->getCurrentPlayers() >= $ev->getMaxPlayers();
            if (!$this->isAdmin() && $isFull) {
                continue;
            }

            $matches[] = $this->mapEntityToView($ev);
        }

        $this->render('sports', [
            'pageTitle'      => 'SportMatch - Sports',
            'activeNav'      => 'sports',
            'selectedSports' => $filters['sports'],
            'sportsGrid'     => $this->getSportsGrid(),
            'matches'        => $matches, // now it is an array of mapped data
            'selectedLevel'  => $filters['level'],
            'selectedLoc'    => $filters['locString'],
            'radiusKm'       => $filters['radius'],
        ]);
    }

    private function mapEntityToView(\Event $ev): array
    {
        $current = $ev->getCurrentPlayers();
        $max = $ev->getMaxPlayers();
        $min = $ev->getMinNeeded();

        $playersText = ($max === null) ? ($current . ' joined') : ($current . ' / ' . $max . ' joined');
        if ($min && $min > 0) {
            // Logic to show constraint note
            // e.g. "Minimum 5" or "Range 5-10"
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

        return [
            'id' => $ev->getId(),
            'title' => $ev->getTitle(),
            'datetime' => $ev->getStartTime() ? (new DateTime($ev->getStartTime()))->format('D, M j, g:i A') : 'TBD',
            'desc' => $ev->getDescription() ?? '',
            'players' => $playersText,
            'level' => $ev->getLevelName() ?? 'Intermediate',
            'levelColor' => $ev->getLevelColor() ?? '#eab308',
            'imageUrl' => $ev->getImageUrl() ?? '',
            'isPast' => $isPast
        ];
    }

    private function parseFilters(): array
    {
        $loc = trim((string)($_GET['loc'] ?? ''));
        $center = null;
        if (preg_match('/^\s*(-?\d+\.?\d*)\s*,\s*(-?\d+\.?\d*)\s*$/', $loc, $m)) {
            $center = ['lat' => (float)$m[1], 'lng' => (float)$m[2]];
        }
        return [
            'sports'    => (array)($_GET['sport'] ?? []),
            'level'     => trim((string)($_GET['level'] ?? 'Any')),
            'locString' => $loc,
            'center'    => $center,
            'radius'    => (float)($_GET['radius'] ?? 10)
        ];
    }

    private function getSportsGrid(): array
    {
        return array_map(fn($s) => [
            'id'   => (int)$s['id'],
            'name' => (string)$s['name'],
            'icon' => $s['icon'] ?: '🏅'
        ], $this->sportsRepository->getAllSports());
    }
}
