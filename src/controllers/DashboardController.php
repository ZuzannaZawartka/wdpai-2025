<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repository/UserRepository.php';
require_once __DIR__ . '/../repository/EventRepository.php';
require_once __DIR__ . '/../repository/SportsRepository.php';
require_once __DIR__ . '/../entity/Event.php';
require_once __DIR__ . '/../entity/User.php';
class DashboardController extends AppController
{
    private UserRepository $userRepository;
    private EventRepository $eventRepository;
    private SportsRepository $sportsRepository;

    protected function __construct()
    {
        parent::__construct();
        $this->userRepository = UserRepository::getInstance();
        $this->eventRepository = EventRepository::getInstance();
        $this->sportsRepository = SportsRepository::getInstance();
    }

    /**
     * Shows the main dashboard page
     * Displays user's upcoming events, favorite sports, and personalized suggestions
     */
    public function index()
    {
        $this->ensureSession();
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

        $upcomingRows = $this->eventRepository->getUserUpcomingEvents($currentUserId, true);
        $upcomingEvents = array_map(function ($r) {
            $ev = new \Event($r);
            $current = (int)$ev->getCurrentPlayers();
            $max = (int)($ev->getMaxPlayers() ?? $current);
            return [
                'id' => $ev->getId(),
                'title' => $ev->getTitle(),
                'datetime' => $ev->getStartTime() ? (new DateTime($ev->getStartTime()))->format('D, M j, g:i A') : '',
                'dateText' => $ev->getStartTime() ? (new DateTime($ev->getStartTime()))->format('D, M j, g:i A') : '',
                'location' => (string)($ev->getLocationText() ?? ''),
                'players' => $current . '/' . $max . ' Players',
                'level' => $ev->getLevelName(),
                'levelColor' => $ev->getLevelColor(),
                'imageUrl' => $ev->getImageUrl(),
            ];
        }, $upcomingRows);

        $favouriteSports = $this->sportsRepository->getDetailedFavouriteSports($currentUserId);


        $suggestions = [];
        if ($currentUser && $currentUser->getLatitude() !== null && $currentUser->getLongitude() !== null) {
            $suggestions = $this->eventRepository->getNearbyEvents(
                (float)$currentUser->getLatitude(),
                (float)$currentUser->getLongitude(),
                3,
                $currentUserId
            );
        }

        $this->render("dashboard", [
            'activeNav' => 'dashboard',
            'upcomingEvents' => $upcomingEvents,
            'favouriteSports' => $favouriteSports,
            'suggestions' => $suggestions
        ]);
    }
}
