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
    
    public function users() {
        $this->requireRole('admin');
        
        $allUsers = $this->userRepository->getUsers();
        
        return $this->render('admin/users', [
            'users' => $allUsers,
            'pageTitle' => 'Manage Users - Admin'
        ]);
    }
    
    public function editUser() {
        $this->requireRole('admin');
        
        $userId = (int)($_GET['id'] ?? 0);
        if (!$userId) {
            header('HTTP/1.1 400 Bad Request');
            return $this->render('404');
        }
        
        if ($this->isPost()) {
            return $this->updateUserProfile($userId);
        }
        
        $user = $this->userRepository->getUserById($userId);
        if (!$user) {
            header('HTTP/1.1 404 Not Found');
            return $this->render('404');
        }
        
        return $this->render('admin/edit-user', [
            'user' => $user,
            'pageTitle' => 'Edit User - Admin'
        ]);
    }
    
    private function updateUserProfile(int $userId): void {
        $user = $this->userRepository->getUserById($userId);
        if (!$user) {
            header('HTTP/1.1 404 Not Found');
            return;
        }
        
        $email = $user['email'];
        $firstName = trim($_POST['firstName'] ?? '');
        $lastName = trim($_POST['lastName'] ?? '');
        $birthDate = trim($_POST['birthDate'] ?? '');
        $newPassword = trim($_POST['newPassword'] ?? '');
        $confirmPassword = trim($_POST['confirmPassword'] ?? '');
        
        // Validate passwords if provided
        if (!empty($newPassword)) {
            if (mb_strlen($newPassword, 'UTF-8') < 8) {
                header('HTTP/1.1 400 Bad Request');
                $this->render('admin/edit-user', [
                    'user' => $user,
                    'messages' => 'Password must be at least 8 characters'
                ]);
                return;
            }
            if ($newPassword !== $confirmPassword) {
                header('HTTP/1.1 400 Bad Request');
                $this->render('admin/edit-user', [
                    'user' => $user,
                    'messages' => 'Passwords do not match'
                ]);
                return;
            }
        }
        
        try {
            $this->userRepository->updateUser($email, [
                'firstname' => $firstName,
                'lastname' => $lastName,
                'birth_date' => $birthDate ?: null
            ]);
            
            // Update password if provided (no old password verification needed for admin)
            if (!empty($newPassword)) {
                $hashedPassword = $this->hashPassword($newPassword);
                $this->userRepository->updateUserPassword($email, $hashedPassword);
            }
            
            header('Location: /admin/users', true, 303);
            exit();
        } catch (Throwable $e) {
            error_log("Admin user update error: " . $e->getMessage());
            header('HTTP/1.1 500 Internal Server Error');
            $this->render('admin/edit-user', [
                'user' => $user,
                'messages' => 'Failed to update user'
            ]);
        }
    }
    
    public function events() {
        $this->requireRole('admin');
        
        // UŻYCIE WIDOKU vw_events_full, pobiera pełne informacje o wszystkich eventach z widoku
        $allEvents = $this->eventRepository->getEventsFromView();
        
        // Map to admin view format
        $enriched = array_map(function($event) {
            return [
                'id' => (int)$event['id'],
                'title' => (string)$event['title'],
                'name' => (string)$event['title'],
                'dateTime' => !empty($event['start_time']) ? (new DateTime($event['start_time']))->format('D, M j, g:i A') : 'TBD',
                'date' => !empty($event['start_time']) ? (new DateTime($event['start_time']))->format('D, M j, g:i A') : 'TBD',
                'location' => (string)($event['location_text'] ?? ''),
                'coords' => isset($event['latitude'], $event['longitude']) ? ($event['latitude'] . ', ' . $event['longitude']) : '',
                'imageUrl' => (string)($event['image_url'] ?? ''),
                'organizer_email' => (string)($event['owner_name'] ?? ''),
                'email' => (string)($event['owner_name'] ?? ''),
                'current_participants' => (int)($event['current_players'] ?? 0),
                'max_participants' => (int)($event['max_players'] ?? 0),
                'participants' => (int)($event['max_players'] ?? 0),
            ];
        }, $allEvents);
        
        return $this->render('admin/events', [
            'events' => $enriched,
            'pageTitle' => 'Manage Events - Admin'
        ]);
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
