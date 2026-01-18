<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repository/SportsRepository.php';
require_once __DIR__ . '/../repository/EventRepository.php';
require_once __DIR__ . '/../entity/Event.php';

class SportsController extends AppController
{
    private SportsRepository $sportsRepository;
    private EventRepository $eventRepository;

    protected function __construct()
    {
        parent::__construct();
        $this->sportsRepository = SportsRepository::getInstance();
        $this->eventRepository = EventRepository::getInstance();
    }

    public function index(): void
    {
        $filters = $this->parseFilters();
        $rawEvents = $this->eventRepository->getFilteredEventsListing($filters, $this->isAdmin());

        $matches = [];
        foreach ($rawEvents as $row) {
            $ev = new \Event($row);

            $isFull = $ev->getMaxPlayers() > 0 && $ev->getCurrentPlayers() >= $ev->getMaxPlayers();
            if (!$this->isAdmin() && $isFull) {
                continue;
            }

            $matches[] = $this->mapEntityToView($ev);
        }

        $this->render('sports', [
            'pageTitle'      => 'FindRival - Sports',
            'activeNav'      => 'sports',
            'selectedSports' => $filters['sports'],
            'sportsGrid'     => $this->getSportsGrid(),
            'matches'        => $matches,
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
            if ($min && $min > 0) {
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

    public function search(): void
    {
        $contentType = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';

        if ($contentType === "application/json") {
            $content = trim(file_get_contents("php://input"));
            $decoded = json_decode($content, true);

            require_once __DIR__ . '/../dto/EventSearchRequestDTO.php';
            require_once __DIR__ . '/../dto/EventResponseDTO.php';

            $criteria = EventSearchRequestDTO::fromRequest($decoded);
            $results = $this->eventRepository->searchEvents($criteria);

            $total = 0;
            if (!empty($results)) {
                $total = (int)$results[0]->getRawData()['total_count'];
            }

            $dtos = array_map(fn($ev) => EventResponseDTO::fromEntity($ev), $results);

            header('Content-Type: application/json');
            http_response_code(200);
            echo json_encode([
                'events' => $dtos,
                'total' => $total,
                'page' => $criteria->page,
                'totalPages' => ceil($total / $criteria->limit)
            ]);
            exit();
        } else {
            http_response_code(415);
            echo json_encode(['error' => 'Unsupported Media Type']);
            exit();
        }
    }
}
