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
        $oldPassword = trim($_POST['oldPassword'] ?? '');
        $newPassword = trim($_POST['newPassword'] ?? '');
        $confirmPassword = trim($_POST['confirmPassword'] ?? '');

        // Validate password change if any password field is filled
        $errors = [];
        if (!empty($oldPassword) || !empty($newPassword) || !empty($confirmPassword)) {
            if (empty($oldPassword)) {
                $errors[] = "Current password is required to change password";
            }
            if (empty($newPassword)) {
                $errors[] = "New password is required";
            }
            if (empty($confirmPassword)) {
                $errors[] = "Password confirmation is required";
            }
            if (strlen($newPassword ?? '') < 8) {
                $errors[] = "New password must be at least 8 characters";
            }
            if ($newPassword !== $confirmPassword) {
                $errors[] = "Passwords do not match";
            }
        }

        if (!empty($errors)) {
            header('HTTP/1.1 400 Bad Request');
            $this->render('profile', ['messages' => implode('<br>', $errors)]);
            return;
        }

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
        
        // Verify old password FIRST if changing password
        if (!empty($newPassword)) {
            try {
                $dbUser = $repo->getUserByEmail($email);
                if (!$dbUser || !$this->verifyPassword($oldPassword, $dbUser['password'])) {
                    header('HTTP/1.1 401 Unauthorized');
                    $allSports = array_values(MockRepository::sportsCatalog());
                    $favouriteSports = MockRepository::favouriteSports($userId);
                    $selectedSportIds = array_values(array_filter(array_map(function($item) {
                        return is_array($item) && isset($item['id']) ? (int)$item['id'] : null;
                    }, $favouriteSports)));
                    $this->render('profile', [
                        'messages' => 'Current password is incorrect',
                        'allSports' => $allSports,
                        'selectedSportIds' => $selectedSportIds
                    ]);
                    return;
                }
            } catch (Throwable $e) {
                error_log("Password verification error: " . $e->getMessage());
                header('HTTP/1.1 500 Internal Server Error');
                $this->render('profile', ['messages' => 'Failed to verify password']);
                return;
            }
        }
        
        try {
            $repo->updateUser($email, [
                'firstname' => $firstName,
                'lastname' => $lastName,
                'birth_date' => $birthDate ?: null,
                'latitude' => $latitude,
                'longitude' => $longitude
            ]);
            
            // Update password if provided (already verified above)
            if (!empty($newPassword)) {
                $hashedPassword = $this->hashPassword($newPassword);
                $repo->updateUserPassword($email, $hashedPassword);
            }
            
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