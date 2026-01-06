<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repository/UserRepository.php';
require_once __DIR__ . '/../repository/EventRepository.php';
require_once __DIR__ . '/../repository/MockRepository.php';

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
        
        $allEvents = MockRepository::events();
        
        // Enrich events with organizer info and participants count
        $users = MockRepository::users();
        $participants = MockRepository::eventParticipants();
        
        $enriched = array_map(function($event) use ($users, $participants) {
            $ownerId = $event['ownerId'] ?? null;
            $organizer = null;
            if ($ownerId && isset($users[$ownerId])) {
                $organizer = $users[$ownerId];
            }
            
            return [
                'id' => $event['id'],
                'title' => $event['title'],
                'name' => $event['title'],
                'dateTime' => $event['dateText'] ?? '',
                'date' => $event['dateText'] ?? '',
                'location' => $event['location'] ?? '',
                'coords' => $event['coords'] ?? '',
                'imageUrl' => $event['imageUrl'] ?? '',
                'organizer_email' => $organizer['email'] ?? '',
                'email' => $organizer['email'] ?? '',
                'current_participants' => count($participants[$event['id']] ?? []),
                'max_participants' => $event['maxPlayers'] ?? 0,
                'participants' => $event['maxPlayers'] ?? 0,
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
            $deleted = MockRepository::deleteEvent($eventId);
            
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
