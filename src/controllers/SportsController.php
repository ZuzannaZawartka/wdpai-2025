<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repository/SportsRepository.php';
require_once __DIR__ . '/../repository/EventRepository.php';

class SportsController extends AppController {
    private SportsRepository $sportsRepository;
    private EventRepository $eventRepository;

    public function __construct() {
        parent::__construct();
        $this->sportsRepository = new SportsRepository();
        $this->eventRepository = new EventRepository();
    }

    public function index(): void {
        $filters = $this->parseFilters();
        $events = $this->eventRepository->getFilteredEventsListing($filters, $this->isAdmin());
        
        if (!$this->isAdmin()) {
            $events = array_filter($events, fn($ev) => !$this->eventRepository->isEventFull((int)$ev['id']));
        }

        $formattedMatches = array_map(fn($ev) => $this->mapEventData($ev), $events);

        $this->render('sports', [
            'pageTitle'      => 'SportMatch - Sports',
            'activeNav'      => 'sports',
            'selectedSports' => $filters['sports'],
            'sportsGrid'     => $this->getSportsGrid(),
            'matches'        => $formattedMatches,
            'selectedLevel'  => $filters['level'],
            'selectedLoc'    => $filters['locString'],
            'radiusKm'       => $filters['radius'],
        ]);
    }

    private function parseFilters(): array {
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

    private function getSportsGrid(): array {
        return array_map(fn($s) => [
            'id'   => (int)$s['id'],
            'name' => (string)$s['name'],
            'icon' => $s['icon'] ?: '🏅'
        ], $this->sportsRepository->getAllSports());
    }
}