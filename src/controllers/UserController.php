<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repository/UserRepository.php';
require_once __DIR__ . '/../repository/MockRepository.php';

class UserController extends AppController {

    public function details($id) {
        $this->render("user",['id'=>$id]);
    }

    public function profile() {
        $this->ensureSession();

        $userId = $this->getCurrentUserId();
        $email = $this->getCurrentUserEmail();

        $repo = new UserRepository();
        $dbUser = null;
        if ($email) {
            try {
                $dbUser = $repo->getUserByEmail($email);
            } catch (Throwable $e) {
                $dbUser = null;
            }
        }

        $profile = [
            'firstName' => '',
            'lastName' => '',
            'email' => $email,
            'location' => '',
            'sports' => [],
            'avatar' => (defined('DEFAULT_AVATAR') ? DEFAULT_AVATAR : '/public/images/avatar-placeholder.jpg')
        ];

        if (is_array($dbUser)) {
            $profile['firstName'] = $dbUser['firstname'] ?? '';
            $profile['lastName'] = $dbUser['lastname'] ?? '';
            $profile['email'] = $dbUser['email'] ?? $email;
        } elseif ($userId) {
            $users = MockRepository::users();
            if (isset($users[$userId])) {
                $name = trim((string)($users[$userId]['name'] ?? ''));
                $parts = preg_split('/\s+/', $name, 2) ?: [];
                $profile['firstName'] = $parts[0] ?? '';
                $profile['lastName'] = $parts[1] ?? '';
                $profile['avatar'] = $users[$userId]['avatar'] ?? $profile['avatar'];
            }
        }

        $this->render("profile", [
            'pageTitle' => 'SportMatch - Profile',
            'activeNav' => 'profile',
            'user' => $profile
        ]);
    }
}