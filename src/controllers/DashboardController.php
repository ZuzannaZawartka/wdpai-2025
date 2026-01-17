<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repository/UserRepository.php';
require_once __DIR__ . '/../repository/EventRepository.php';
require_once __DIR__ . '/../repository/SportsRepository.php';
require_once __DIR__ . '/../entity/Event.php';
require_once __DIR__ . '/../entity/User.php';

class DashboardController extends AppController {

    private $cardRepository;
    private UserRepository $userRepository;

    public function __construct() {
        $this->userRepository = new UserRepository();
    }


    public function index(?int $id =null) {
        // Uprawnienia obsługuje Routing.php
        
        $currentUserId = $this->getCurrentUserId();
        $currentUser = $currentUserId ? $this->userRepository->getUserEntityById($currentUserId) : null;

        $locationOverride = null;
        if ($currentUser) {
            $lat = $currentUser->getLatitude();
            $lng = $currentUser->getLongitude();
            if (is_numeric($lat) && is_numeric($lng)) {
                $locationOverride = ['lat' => (float)$lat, 'lng' => (float)$lng];
            }
        }

        $eventsRepo = new EventRepository();
        $upcomingRows = $currentUserId ? $eventsRepo->getUserUpcomingEvents($currentUserId) : [];
        $upcomingEvents = array_map(function($r) {
            $ev = new \Event($r);
            $current = (int)($ev->getCurrentPlayers() ?? 0);
            $max = (int)($ev->getMaxPlayers() ?? $current);
            $level = $ev->getLevelName() ?? 'Intermediate';
            return [
                'id' => $ev->getId(),
                'title' => $ev->getTitle(),
                'datetime' => $ev->getStartTime() ? (new DateTime($ev->getStartTime()))->format('D, M j, g:i A') : '',
                'dateText' => $ev->getStartTime() ? (new DateTime($ev->getStartTime()))->format('D, M j, g:i A') : '',
                'location' => (string)($ev->getLocationText() ?? ''),
                'players' => $current . '/' . $max . ' Players',
                'level' => $level,
                'levelColor' => $level === 'Beginner' ? '#22c55e' : ($level === 'Advanced' ? '#ef4444' : '#eab308'),
                'imageUrl' => (string)($ev->getImageUrl() ?? ''),
            ];
        }, $upcomingRows);

        $sportsRepo = new SportsRepository();
        $favIds = $currentUserId ? $sportsRepo->getFavouriteSportsIds($currentUserId) : [];
        $favouriteSports = array_map(function($sid) use ($sportsRepo) {
            $name = $this->lookupSportName($sid, $sportsRepo);
            return [
                'id' => $sid,
                'name' => $name,
                'icon' => $this->sportIcon($name),
                'nearbyText' => ''
            ];
        }, $favIds);

        // Suggestions: compute by distance in PHP
        $suggestions = [];
        if ($locationOverride) {
            $rows = $eventsRepo->getUpcomingWithCoords();
            $enriched = [];
            foreach ($rows as $ev) {
                $lat2 = (float)($ev['latitude'] ?? 0);
                $lng2 = (float)($ev['longitude'] ?? 0);
                $dist = $this->distanceKm((float)$locationOverride['lat'], (float)$locationOverride['lng'], $lat2, $lng2);
                if (!is_finite($dist)) { continue; }
                $enriched[] = [
                    'id' => (int)$ev['id'],
                    'title' => (string)$ev['title'],
                    'sport' => (string)($ev['sport_name'] ?? 'Sport'),
                    'distanceText' => sprintf('%s (%.1f km away)', (string)($ev['location_text'] ?? ''), round($dist,1)),
                    'imageUrl' => (string)($ev['image_url'] ?? ''),
                    'cta' => 'See Details',
                    'distanceKm' => $dist
                ];
            }
            usort($enriched, fn($a,$b) => ($a['distanceKm'] ?? INF) <=> ($b['distanceKm'] ?? INF));
            $suggestions = array_map(function($x){ unset($x['distanceKm']); return $x; }, array_slice($enriched, 0, 3));
        }

        $this->render("dashboard",  [
            'activeNav' => 'dashboard',
            'pageTitle' => 'SportMatch Dashboard',
            'upcomingEvents' => $upcomingEvents,
            'favouriteSports' => $favouriteSports,
            'suggestions' => $suggestions
        ]);
    }

    private function lookupSportName(int $id, SportsRepository $repo): string {
        static $cache = null;
        if ($cache === null) {
            $cache = [];
            foreach ($repo->getAllSports() as $s) { $cache[(int)$s['id']] = $s['name']; }
        }
        return $cache[$id] ?? 'Sport';
    }

    private function sportIcon(string $name): string {
        switch ($name) {
            case 'Soccer': return '⚽';
            case 'Basketball': return '🏀';
            case 'Tennis': return '🎾';
            case 'Running': return '🏃';
            case 'Cycling': return '🚴';
            default: return '🏅';
        }
    }

    private function distanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float {
        $earthRadius = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        return $earthRadius * $c;
    }

    public function search()  {

        $contentType = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';

        if ($contentType !== "application/json") {
            $this->setStatusCode(415);
            header('Content-Type: application/json');
            echo json_encode(["status"=> 415,"message" => "Content type must be: application/json"]);
            return;
        }

        if ($this->isPost()) {
            $this->respondMethodNotAllowed('Method not allowed', true);
        }
        header('Content-Type: application/json');
        $this->setStatusCode(200);

        $content = trim(file_get_contents("php://input"));
        $decoded = json_decode($content, true);



        $cards =$this->cardRepository->getCardsByTitle($decoded['search']);
        echo json_encode($cards);
    }

}