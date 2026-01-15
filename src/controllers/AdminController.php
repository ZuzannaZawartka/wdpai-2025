<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repository/UserRepository.php';
require_once __DIR__ . '/../repository/EventRepository.php';
require_once __DIR__ . '/../repository/SportsRepository.php';
require_once __DIR__ . '/../validators/UserFormValidator.php';
require_once __DIR__ . '/../repository/SportsRepository.php';

class AdminController extends AppController {
    
    private UserRepository $userRepository;
    private EventRepository $eventRepository;
    
    public function __construct() {
        $this->userRepository = new UserRepository();
        $this->eventRepository = new EventRepository();
    }
    
    public function accounts() {
        $this->requireRole('admin');

        $allUsers = $this->userRepository->getUsers();
        $stats = $this->userRepository->getUsersStatistics();
        $statsById = [];
        foreach ($stats as $stat) {
            $statsById[(int)$stat['id']] = $stat;
        }
        foreach ($allUsers as &$user) {
            $uid = (int)($user['id'] ?? 0);
            if (isset($statsById[$uid])) {
                $user['events_joined_count'] = $statsById[$uid]['events_joined_count'] ?? 0;
                $user['events_created_count'] = $statsById[$uid]['events_created_count'] ?? 0;
            }
        }
        unset($user);

        return $this->render('admin/accounts', [
            'users' => $allUsers,
            'pageTitle' => 'Manage Users - Admin'
        ]);
    }
    
    public function editUser() {
        // Przekierowanie do UserController
        (new UserController())->editUserByAdmin();
        return;
    }
    
    
    
    public function deleteEvent() {
        // Przekierowanie do EventController
        (new EventController())->deleteEventByAdmin();
        return;
    }
    
    private function isDelete(): bool {
        return $_SERVER['REQUEST_METHOD'] === 'DELETE';
    }
}
