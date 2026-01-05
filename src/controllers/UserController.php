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
            'birthDate' => '',
            'location' => '',
            'latitude' => null,
            'longitude' => null,
            'sports' => [],
            'avatar' => DEFAULT_AVATAR
        ];

        if (is_array($dbUser)) {
            $profile['firstName'] = $dbUser['firstname'] ?? '';
            $profile['lastName'] = $dbUser['lastname'] ?? '';
            $profile['email'] = $dbUser['email'] ?? $email;
            $profile['birthDate'] = $dbUser['birth_date'] ?? '';
            $profile['latitude'] = $dbUser['latitude'] ?? null;
            $profile['longitude'] = $dbUser['longitude'] ?? null;
            if ($profile['latitude'] && $profile['longitude']) {
                $profile['location'] = $profile['latitude'] . ', ' . $profile['longitude'];
            }
        } elseif ($userId) {
            $users = MockRepository::users();
            if (isset($users[$userId])) {
                $name = trim((string)($users[$userId]['name'] ?? ''));
                $parts = preg_split('/\s+/', $name, 2) ?: [];
                $profile['firstName'] = $parts[0] ?? '';
                $profile['lastName'] = $parts[1] ?? '';
                $profile['avatar'] = $users[$userId]['avatar'] ?? $profile['avatar'];
                // Convert stored lat/lng to display string
                if (isset($users[$userId]['location']['lat'], $users[$userId]['location']['lng'])) {
                    $profile['location'] = $users[$userId]['location']['lat'] . ', ' . $users[$userId]['location']['lng'];
                }
            }
        }

        $favouriteSports = MockRepository::favouriteSports($userId);
        $allSports = array_values(MockRepository::sportsCatalog());
        $selectedSportIds = array_values(array_filter(array_map(function($item) {
            return is_array($item) && isset($item['id']) ? (int)$item['id'] : null;
        }, $favouriteSports)));

        $this->render("profile", [
            'pageTitle' => 'SportMatch - Profile',
            'activeNav' => 'profile',
            'user' => $profile,
            'favouriteSports' => $favouriteSports,
            'allSports' => $allSports,
            'selectedSportIds' => $selectedSportIds
        ]);
    }

    public function updateFavourites(): void {
        // Disabled - favourite sports are updated via updateProfile() on form save only
        http_response_code(405);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    }

    public function updateProfile(): void {
        $this->requireAuth();
        $this->ensureSession();

        if (!$this->isPost()) {
            header('HTTP/1.1 405 Method Not Allowed');
            http_response_code(405);
            return;
        }

        $userId = $this->getCurrentUserId();
        $email = $this->getCurrentUserEmail();

        if (!$userId || !$email) {
            header('Location: /login', true, 303);
            exit();
        }

        $firstName = trim($_POST['firstName'] ?? '');
        $lastName = trim($_POST['lastName'] ?? '');
        $birthDate = trim($_POST['birthDate'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $favouriteSports = array_map('intval', $_POST['favourite_sports'] ?? []);

        // Parse location (lat, lng)
        $latitude = null;
        $longitude = null;
        if (!empty($location) && str_contains($location, ',')) {
            $parts = array_map('trim', explode(',', $location));
            if (count($parts) === 2 && is_numeric($parts[0]) && is_numeric($parts[1])) {
                $latitude = (float)$parts[0];
                $longitude = (float)$parts[1];
            }
        }

        $repo = new UserRepository();
        try {
            $repo->updateUser($email, [
                'firstname' => $firstName,
                'lastname' => $lastName,
                'birth_date' => $birthDate ?: null,
                'latitude' => $latitude,
                'longitude' => $longitude
            ]);
            
            // Update favourite sports
            if (!empty($favouriteSports)) {
                MockRepository::setUserFavouriteSports($userId, $favouriteSports);
            }
        } catch (Throwable $e) {
            error_log("Profile update error: " . $e->getMessage());
            header('HTTP/1.1 500 Internal Server Error');
            $this->render('profile', ['messages' => 'Failed to update profile']);
            return;
        }

        header('Location: /profile', true, 303);
        exit();
    }
}