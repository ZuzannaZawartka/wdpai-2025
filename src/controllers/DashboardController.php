<?php

class DashboardController extends AppController {
    private $cardRepository;
    private UserRepository $userRepository;
    private EventRepository $eventRepository;
    private SportsRepository $sportsRepository;

    public function __construct() {
        parent::__construct();
        $this->userRepository = new UserRepository();
        $this->eventRepository = new EventRepository();
        $this->sportsRepository = new SportsRepository();
    }

    public function index() {
        $this->ensureSession();
        $currentUserId = $this->getCurrentUserId();
        $currentUser = $this->userRepository->getUserProfileById($currentUserId);


        $upcomingRows = $this->eventRepository->getUserUpcomingEvents($currentUserId);
        $upcomingEvents = array_map(function($r) {
            return [
                'id' => (int)$r['id'],
                'title' => (string)$r['title'],
                'dateText' => (new DateTime($r['start_time']))->format('D, M j, g:i A'),
                'location' => (string)($r['location_text'] ?? ''),
                'players' => ($r['current_players'] ?? 0) . '/' . ($r['max_players'] ?? 0),
                'level' => $r['level_name'],
                'levelColor' => $r['level_color'] ?? '#eab308', 
                'imageUrl' => $r['image_url'] ?? 'public/img/uploads/default.jpg'
            ];
        }, $upcomingRows);

        $favouriteSports = $this->sportsRepository->getDetailedFavouriteSports($currentUserId);


        $suggestions = [];
        if ($currentUser && isset($currentUser['latitude'], $currentUser['longitude'])) {
            $suggestions = $this->eventRepository->getNearbyEvents(
                (float)$currentUser['latitude'], 
                (float)$currentUser['longitude'], 
                3 
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