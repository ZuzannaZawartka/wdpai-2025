<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repository/UserRepository.php';
require_once __DIR__ . '/../repository/EventRepository.php';

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
        $this->requireRole('admin');
        $userId = (int)($_GET['id'] ?? 0);
        if (!$userId) {
            http_response_code(400);
            return $this->render('404');
        }
        if ($this->isPost()) {
            return $this->updateUserProfile($userId);
        }
        $user = $this->userRepository->getUserById($userId);
        if (!$user) {
            http_response_code(404);
            return $this->render('404');
        }

        $user['firstName'] = $user['firstname'] ?? '';
        $user['lastName'] = $user['lastname'] ?? '';
        $user['birthDate'] = $user['birth_date'] ?? '';
        if (!empty($user['latitude']) && !empty($user['longitude'])) {
            $user['location'] = $user['latitude'] . ', ' . $user['longitude'];
        } else {
            $user['location'] = '';
        }
       

        require_once __DIR__ . '/../repository/SportsRepository.php';
        $sportsRepo = new SportsRepository();
        $allSports = $sportsRepo->getAllSports();
        $selectedSportIds = $sportsRepo->getFavouriteSportsIds($userId);
        return $this->render('admin/edit-user', [
            'user' => $user,
            'allSports' => $allSports,
            'selectedSportIds' => $selectedSportIds,
            'pageTitle' => 'Edit User - Admin'
        ]);
    }
    
    private function updateUserProfile(int $userId): void {
        $user = $this->userRepository->getUserById($userId);
        if (!$user) {
            http_response_code(404);
            return;
        }
        
        $email = $user['email'];
        $firstName = trim($_POST['firstName'] ?? '');
        $lastName = trim($_POST['lastName'] ?? '');
        $birthDate = trim($_POST['birthDate'] ?? '');
        $newPassword = trim($_POST['newPassword'] ?? '');
        $confirmPassword = trim($_POST['confirmPassword'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $latitude = null;
        $longitude = null;
        if ($location && strpos($location, ',') !== false) {
            $parts = explode(',', $location);
            if (count($parts) === 2) {
                $lat = floatval(trim($parts[0]));
                $lng = floatval(trim($parts[1]));
                if ($lat !== 0.0 && $lng !== 0.0) {
                    $latitude = $lat;
                    $longitude = $lng;
                }
            }
        }

        if (!empty($newPassword)) {
            if (mb_strlen($newPassword, 'UTF-8') < 8) {
                http_response_code(400);
                $this->render('admin/edit-user', [
                    'user' => $user,
                    'messages' => 'Password must be at least 8 characters'
                ]);
                return;
            }
            if ($newPassword !== $confirmPassword) {
                http_response_code(400);
                $this->render('admin/edit-user', [
                    'user' => $user,
                    'messages' => 'Passwords do not match'
                ]);
                return;
            }
        }
        try {
            $updateData = [
                'firstname' => $firstName,
                'lastname' => $lastName,
                'birth_date' => $birthDate ?: null
            ];
            if ($latitude !== null && $longitude !== null) {
                $updateData['latitude'] = $latitude;
                $updateData['longitude'] = $longitude;
            }
            $this->userRepository->updateUser($email, $updateData);
           
            if (!empty($newPassword)) {
                $hashedPassword = $this->hashPassword($newPassword);
                $this->userRepository->updateUserPassword($email, $hashedPassword);
            }
           
            header('Location: /accounts', true, 303);
            exit();
        } catch (Throwable $e) {
            error_log("Admin user update error: " . $e->getMessage());
            http_response_code(500);
            $this->render('admin/edit-user', [
                'user' => $user,
                'messages' => 'Failed to update user'
            ]);
        }
    }
    
    
    public function deleteEvent() {
        $this->requireRole('admin');
        
        if (!$this->isPost() && !$this->isDelete()) {
            header('HTTP/1.1 405 Method Not Allowed');
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }
        
        $eventId = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        if (!$eventId) {
            header('HTTP/1.1 400 Bad Request');
            echo json_encode(['error' => 'Event ID required']);
            return;
        }
        
        try {
            $deleted = $this->eventRepository->deleteEvent($eventId);
            
            if ($deleted) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true]);
            } else {
                header('HTTP/1.1 404 Not Found');
                echo json_encode(['error' => 'Event not found']);
            }
        } catch (Throwable $e) {
            error_log("Admin event delete error: " . $e->getMessage());
            header('HTTP/1.1 500 Internal Server Error');
            echo json_encode(['error' => 'Failed to delete event']);
        }
        exit();
    }
    
    private function isDelete(): bool {
        return $_SERVER['REQUEST_METHOD'] === 'DELETE';
    }
}
