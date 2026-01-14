<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repository/UserRepository.php';
require_once __DIR__ . '/../repository/SportsRepository.php';

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
            'age' => null,
            'location' => '',
            'latitude' => null,
            'longitude' => null,
            'sports' => [],
            'avatar' => DEFAULT_AVATAR,
            'statistics' => null,
            'role' => 'user'
        ];

        if (is_array($dbUser)) {
            $profile['firstName'] = $dbUser['firstname'] ?? '';
            $profile['lastName'] = $dbUser['lastname'] ?? '';
            $profile['email'] = $dbUser['email'] ?? $email;
            $profile['birthDate'] = $dbUser['birth_date'] ?? '';
            $profile['latitude'] = $dbUser['latitude'] ?? null;
            $profile['longitude'] = $dbUser['longitude'] ?? null;
            $profile['role'] = $dbUser['role'] ?? 'user';
            if (!empty($dbUser['avatar_url'])) {
                $profile['avatar'] = $dbUser['avatar_url'];
            }
        }
        
        if ($profile['latitude'] && $profile['longitude']) {
            $profile['location'] = $profile['latitude'] . ', ' . $profile['longitude'];
        }
        
        // Użycie funkcji calculate_user_age - pobierz wiek użytkownika
        if ($userId) {
            $profile['age'] = $repo->getUserAge($userId);
        }
        
        // Użycie widoku vw_user_stats - pobierz statystyki użytkownika
        if ($userId) {
            $profile['statistics'] = $repo->getUserStatisticsById($userId);
        }
        
        if ($profile['latitude'] && $profile['longitude']) {
            $profile['location'] = $profile['latitude'] . ', ' . $profile['longitude'];
        }
        // Keep session avatar in sync for header on other pages
        $_SESSION['user_avatar'] = $profile['avatar'] ?: ($_SESSION['user_avatar'] ?? DEFAULT_AVATAR);
        
        $sportsRepo = new SportsRepository();
        $allSports = $sportsRepo->getAllSports();
        $selectedSportIds = $userId ? $sportsRepo->getFavouriteSportsIds($userId) : [];
        
        // Build sport id to icon map
        $sportIdToIcon = [];
        foreach ($allSports as $sport) {
            $sportIdToIcon[(int)$sport['id']] = $sport['icon'] ?? '🏅';
        }

        $this->render("profile", [
            'pageTitle' => 'SportMatch - Profile',
            'activeNav' => 'profile',
            'user' => $profile,
            'allSports' => $allSports,
            'selectedSportIds' => $selectedSportIds,
            'sportIdToIcon' => $sportIdToIcon
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
        $isAdmin = ($_SESSION['user_role'] ?? null) === 'admin';
        if (!empty($oldPassword) || !empty($newPassword) || !empty($confirmPassword)) {
            if (!$isAdmin && empty($oldPassword)) {
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
        $existingUser = $repo->getUserByEmail($email);
        $existingAvatar = $existingUser['avatar_url'] ?? null;
        
        // Verify old password FIRST if changing password
        $isAdmin = ($_SESSION['user_role'] ?? null) === 'admin';
        if (!empty($newPassword) && !$isAdmin) {
            try {
                $dbUser = $existingUser;
                if (!$dbUser || !$this->verifyPassword($oldPassword, $dbUser['password'])) {
                    header('HTTP/1.1 401 Unauthorized');
                    $allSports = (new SportsRepository())->getAllSports();
                    $selectedSportIds = $userId ? (new SportsRepository())->getFavouriteSportsIds($userId) : [];
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

        // Handle avatar upload (optional)
        $avatarPath = $existingAvatar;
        if (isset($_FILES['avatar']) && isset($_FILES['avatar']['tmp_name']) && is_uploaded_file($_FILES['avatar']['tmp_name'])) {
            $file = $_FILES['avatar'];
            if ($file['error'] === UPLOAD_ERR_OK) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : '';
                if ($finfo) {
                    finfo_close($finfo);
                }

                $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (in_array($mime, $allowed, true)) {
                    $ext = pathinfo($file['name'] ?? '', PATHINFO_EXTENSION);
                    $safeExt = preg_replace('/[^a-zA-Z0-9]/', '', $ext) ?: 'jpg';
                    $targetDir = __DIR__ . '/../../public/images/avatars';
                    if (!is_dir($targetDir)) {
                        @mkdir($targetDir, 0775, true);
                    }
                    $unique = bin2hex(random_bytes(8));
                    $fileName = 'avatar_' . $unique . '.' . $safeExt;
                    $targetPath = $targetDir . '/' . $fileName;
                    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                        $avatarPath = '/public/images/avatars/' . $fileName;
                    }
                }
            }
        }
        
        try {
            $repo->updateUser($email, [
                'firstname' => $firstName,
                'lastname' => $lastName,
                'birth_date' => $birthDate ?: null,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'avatar_url' => $avatarPath
            ]);
            
            // Update password if provided (already verified above)
            if (!empty($newPassword)) {
                $hashedPassword = $this->hashPassword($newPassword);
                $repo->updateUserPassword($email, $hashedPassword);
            }
            
            // Update favourite sports
            (new SportsRepository())->setFavouriteSports($userId, $favouriteSports);

            // Update session avatar for header usage
            if (!empty($avatarPath)) {
                $_SESSION['user_avatar'] = $avatarPath;
            }
        } catch (Throwable $e) {
            error_log("Profile update error: " . $e->getMessage());
            header('HTTP/1.1 500 Internal Server Error');
            $this->render('profile', ['messages' => 'Failed to update profile']);
            return;
        }

        header('Location: /profile', true, 303);
        // Nie wywołuj exit() by umożliwić dalsze przetwarzanie lub testy
    }
    
    public function editUser($userId = null) {
        $this->ensureSession();
        // Uprawnienia obsługuje Routing.php
        $editUserId = (int)($_GET['id'] ?? $userId ?? 0);
        if (!$editUserId) {
            http_response_code(400);
            $this->render('404');
            return;
        }
        
        $repo = new UserRepository();
        $sportsRepo = new SportsRepository();
        $allSports = $sportsRepo->getAllSports();
        $selectedSportIds = $editUserId ? $sportsRepo->getFavouriteSportsIds($editUserId) : [];

        if ($this->isPost()) {
            $user = $repo->getUserById($editUserId);
            if (!$user) {
                header('HTTP/1.1 404 Not Found');
                $this->render('404');
                return;
            }

            require_once __DIR__ . '/../validators/UserFormValidator.php';
            $validation = UserFormValidator::validate($_POST);
            $errors = $validation['errors'] ?? [];
            $validated = $validation['data'] ?? [];

            $newPassword = trim($_POST['newPassword'] ?? '');
            $confirmPassword = trim($_POST['confirmPassword'] ?? '');
            $favouriteSports = array_map('intval', $_POST['favourite_sports'] ?? []);

            if (!empty($errors)) {
                header('HTTP/1.1 400 Bad Request');
                $this->render('user-edit', [
                    'user' => $user,
                    'allSports' => $allSports,
                    'selectedSportIds' => $selectedSportIds,
                    'messages' => implode('<br>', $errors)
                ]);
                return;
            }

            try {
                $repo->updateUser($user['email'], [
                    'firstname' => $validated['firstName'],
                    'lastname' => $validated['lastName'],
                    'birth_date' => $validated['birthDate'] ?: null
                ]);
                if (!empty($validated['newPassword'])) {
                    $hashedPassword = $this->hashPassword($validated['newPassword']);
                    $repo->updateUserPassword($user['email'], $hashedPassword);
                }
                // Update favourite sports
                $sportsRepo->setFavouriteSports($editUserId, $favouriteSports);
                header('Location: /joined', true, 303);
                exit();
            } catch (Throwable $e) {
                error_log("Admin user update error: " . $e->getMessage());
                header('HTTP/1.1 500 Internal Server Error');
                $this->render('user-edit', [
                    'user' => $user,
                    'allSports' => $allSports,
                    'selectedSportIds' => $selectedSportIds,
                    'messages' => 'Failed to update user'
                ]);
            }
        } else {
            $user = $repo->getUserById($editUserId);
            if (!$user) {
                header('HTTP/1.1 404 Not Found');
                $this->render('404');
                return;
            }

            if (empty($user['location']) && !empty($user['latitude']) && !empty($user['longitude'])) {
                $user['location'] = $user['latitude'] . ', ' . $user['longitude'];
            }
            $this->render('user-edit', [
                'user' => $user,
                'allSports' => $allSports,
                'selectedSportIds' => $selectedSportIds,
                'pageTitle' => 'My Profile'
            ]);
        }
    }
}