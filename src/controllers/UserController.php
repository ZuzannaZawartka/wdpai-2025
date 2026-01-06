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
            if (!empty($dbUser['avatar'])) {
                $profile['avatar'] = $dbUser['avatar'];
            }
            if ($profile['latitude'] && $profile['longitude']) {
                $profile['location'] = $profile['latitude'] . ', ' . $profile['longitude'];
            }
            // Keep session avatar in sync for header on other pages
            $_SESSION['user_avatar'] = $profile['avatar'] ?: ($_SESSION['user_avatar'] ?? DEFAULT_AVATAR);
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
                $_SESSION['user_avatar'] = $profile['avatar'] ?: ($_SESSION['user_avatar'] ?? DEFAULT_AVATAR);
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
        $existingUser = $repo->getUserByEmail($email);
        $existingAvatar = $existingUser['avatar'] ?? null;
        
        // Verify old password FIRST if changing password
        if (!empty($newPassword)) {
            try {
                $dbUser = $existingUser;
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
                'avatar' => $avatarPath
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
        exit();
    }
    
    public function editUser($userId = null) {
        $this->ensureSession();
        
        // Only admin can edit other users
        if (!$this->isAdmin()) {
            header('HTTP/1.1 403 Forbidden');
            $this->render('404');
            return;
        }
        
        $editUserId = (int)($_GET['id'] ?? $userId ?? 0);
        if (!$editUserId) {
            header('HTTP/1.1 400 Bad Request');
            $this->render('404');
            return;
        }
        
        $repo = new UserRepository();
        
        if ($this->isPost()) {
            $user = $repo->getUserById($editUserId);
            if (!$user) {
                header('HTTP/1.1 404 Not Found');
                $this->render('404');
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
                    $this->render('user-edit', [
                        'user' => $user,
                        'messages' => 'Password must be at least 8 characters'
                    ]);
                    return;
                }
                if ($newPassword !== $confirmPassword) {
                    header('HTTP/1.1 400 Bad Request');
                    $this->render('user-edit', [
                        'user' => $user,
                        'messages' => 'Passwords do not match'
                    ]);
                    return;
                }
            }
            
            try {
                $repo->updateUser($email, [
                    'firstname' => $firstName,
                    'lastname' => $lastName,
                    'birth_date' => $birthDate ?: null
                ]);
                
                if (!empty($newPassword)) {
                    $hashedPassword = $this->hashPassword($newPassword);
                    $repo->updateUserPassword($email, $hashedPassword);
                }
                
                header('Location: /joined', true, 303);
                exit();
            } catch (Throwable $e) {
                error_log("Admin user update error: " . $e->getMessage());
                header('HTTP/1.1 500 Internal Server Error');
                $this->render('user-edit', [
                    'user' => $user,
                    'messages' => 'Failed to update user'
                ]);
            }
        } else {
            // GET request - show edit form
            $user = $repo->getUserById($editUserId);
            if (!$user) {
                header('HTTP/1.1 404 Not Found');
                $this->render('404');
                return;
            }
            
            $this->render('user-edit', [
                'user' => $user,
                'pageTitle' => 'Edit User'
            ]);
        }
    }
}