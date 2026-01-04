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
            'avatar' => DEFAULT_AVATAR
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
        $this->requireAuth();
        header('Content-Type: application/json');

        $userId = $this->getCurrentUserId();
        if (!$userId) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
            return;
        }

        $payload = json_decode(file_get_contents('php://input'), true) ?? [];
        $sports = $payload['sports'] ?? [];
        if (!is_array($sports)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid payload']);
            return;
        }

        MockRepository::setUserFavouriteSports($userId, $sports);
        $favouriteSports = MockRepository::favouriteSports($userId);

        echo json_encode([
            'status' => 'success',
            'favouriteSports' => $favouriteSports
        ]);
    }
}