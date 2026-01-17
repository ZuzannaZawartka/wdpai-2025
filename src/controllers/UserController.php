<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repository/UserRepository.php';
require_once __DIR__ . '/../repository/SportsRepository.php';
require_once __DIR__ . '/../validators/UserFormValidator.php';
require_once __DIR__ . '/../entity/User.php';

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

        // Default profile shape (kept for views compatibility)
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

        // Use entity for display when available
        if (is_array($dbUser)) {
            $userEntity = new \User($dbUser);
            $profile['firstName'] = $userEntity->getFirstname() ?? '';
            $profile['lastName'] = $userEntity->getLastname() ?? '';
            $profile['email'] = $userEntity->getEmail() ?? $email;
            $profile['birthDate'] = $userEntity->getBirthDate() ?? '';
            $profile['latitude'] = $userEntity->getLatitude();
            $profile['longitude'] = $userEntity->getLongitude();
            $profile['role'] = $userEntity->getRole() ?? 'user';
            if (!empty($userEntity->getAvatarUrl())) {
                $profile['avatar'] = $userEntity->getAvatarUrl();
            }
        }
        
        if ($profile['latitude'] && $profile['longitude']) {
            $profile['location'] = $profile['latitude'] . ', ' . $profile['longitude'];
        }
        
        if ($userId) {
            $profile['age'] = $repo->getUserAge($userId);
        }
        
        if ($userId) {
            $profile['statistics'] = $repo->getUserStatisticsById($userId);
        }
        
        if ($profile['latitude'] && $profile['longitude']) {
            $profile['location'] = $profile['latitude'] . ', ' . $profile['longitude'];
        }
    
        $_SESSION['user_avatar'] = $profile['avatar'] ?: ($_SESSION['user_avatar'] ?? DEFAULT_AVATAR);
        
        $sportsRepo = new SportsRepository();
        $allSports = $sportsRepo->getAllSports();
        $selectedSportIds = $userId ? $sportsRepo->getFavouriteSportsIds($userId) : [];
        
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
        $this->respondMethodNotAllowed('Method not allowed', true);
    }

    public function updateProfile(): void {
        $this->requireAuth();
        $this->ensureSession();

        if (!$this->isPost()) {
            $this->setStatusCode(405);
            return;
        }

        $repo = new UserRepository();
        $sportsRepo = new SportsRepository();

        $userId = $this->getCurrentUserId();
        $email = $this->getCurrentUserEmail();
        if (!$userId || !$email) {
            header('Location: /login', true, 303);
            exit();
        }

        $location = trim($_POST['location'] ?? '');
        $favouriteSports = array_map('intval', $_POST['favourite_sports'] ?? []);
        $validation = UserFormValidator::validate($_POST);
        $errors = $validation['errors'] ?? [];
        // Prefer DTO if available for stronger typing
        $validated = [];
        if (!empty($validation['dto']) && $validation['dto'] instanceof \UpdateUserDTO) {
            $validated = $validation['dto']->toArray();
        } else {
            $validated = $validation['data'] ?? [];
        }
        $oldPassword = trim($_POST['oldPassword'] ?? '');
        $newPassword = $validated['newPassword'] ?? '';
        $isAdmin = ($_SESSION['user_role'] ?? null) === 'admin';


        $latitude = null;
        $longitude = null;
        if (!empty($location) && str_contains($location, ',')) {
            $parts = array_map('trim', explode(',', $location));
            if (count($parts) === 2 && is_numeric($parts[0]) && is_numeric($parts[1])) {
                $latitude = (float)$parts[0];
                $longitude = (float)$parts[1];
            }
        }

        // Keep raw DB array for operations that need password or direct fields
        $existingUser = $repo->getUserByEmail($email);
        $existingAvatar = $existingUser['avatar_url'] ?? null;

        if (!empty($newPassword) && !$isAdmin && empty($oldPassword)) {
            $errors[] = 'Current password is required to change password';
        }
        if (!empty($errors)) {
            $profile = [
                'firstName' => $validated['firstName'] ?? ($existingUser['firstname'] ?? ''),
                'lastName' => $validated['lastName'] ?? ($existingUser['lastname'] ?? ''),
                'email' => $email,
                'birthDate' => $validated['birthDate'] ?? ($existingUser['birth_date'] ?? ''),
                'location' => $location,
                'latitude' => $existingUser['latitude'] ?? null,
                'longitude' => $existingUser['longitude'] ?? null,
                'sports' => [],
                'avatar' => $existingUser['avatar_url'] ?? DEFAULT_AVATAR,
                'statistics' => $existingUser['statistics'] ?? null,
                'role' => $existingUser['role'] ?? 'user'
            ];
            $allSports = $sportsRepo->getAllSports();
            $selectedSportIds = $userId ? $sportsRepo->getFavouriteSportsIds($userId) : [];
            $sportIdToIcon = [];
            foreach ($allSports as $sport) {
                $sportIdToIcon[(int)$sport['id']] = $sport['icon'] ?? '🏅';
            }
            $this->setStatusCode(400);
            $this->render('profile', [
                'pageTitle' => 'SportMatch - Profile',
                'activeNav' => 'profile',
                'user' => $profile,
                'allSports' => $allSports,
                'selectedSportIds' => $selectedSportIds,
                'sportIdToIcon' => $sportIdToIcon,
                'messages' => implode('<br>', $errors)
            ]);
            return;
        }

        if (!empty($newPassword) && !$isAdmin) {
            if (!$existingUser || !$this->verifyPassword($oldPassword, $existingUser['password'])) {
                $profile = [
                    'firstName' => $validated['firstName'] ?? ($existingUser['firstname'] ?? ''),
                    'lastName' => $validated['lastName'] ?? ($existingUser['lastname'] ?? ''),
                    'email' => $email,
                    'birthDate' => $validated['birthDate'] ?? ($existingUser['birth_date'] ?? ''),
                    'location' => $location,
                    'latitude' => $existingUser['latitude'] ?? null,
                    'longitude' => $existingUser['longitude'] ?? null,
                    'sports' => [],
                    'avatar' => $existingUser['avatar_url'] ?? DEFAULT_AVATAR,
                    'statistics' => $existingUser['statistics'] ?? null,
                    'role' => $existingUser['role'] ?? 'user'
                ];
                $allSports = $sportsRepo->getAllSports();
                $selectedSportIds = $userId ? $sportsRepo->getFavouriteSportsIds($userId) : [];
                $sportIdToIcon = [];
                foreach ($allSports as $sport) {
                    $sportIdToIcon[(int)$sport['id']] = $sport['icon'] ?? '🏅';
                }
                $this->setStatusCode(401);
                $this->render('profile', [
                    'pageTitle' => 'SportMatch - Profile',
                    'activeNav' => 'profile',
                    'user' => $profile,
                    'allSports' => $allSports,
                    'selectedSportIds' => $selectedSportIds,
                    'sportIdToIcon' => $sportIdToIcon,
                    'messages' => 'Current password is incorrect'
                ]);
                return;
            }
        }

        $avatarPath = $existingAvatar;
        if (isset($_FILES['avatar']) && isset($_FILES['avatar']['tmp_name']) && is_uploaded_file($_FILES['avatar']['tmp_name'])) {
            $file = $_FILES['avatar'];
            if ($file['error'] === UPLOAD_ERR_OK) {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo ? $finfo->file($file['tmp_name']) : '';
                unset($finfo);
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
                'firstname' => $validated['firstName'] ?? null,
                'lastname' => $validated['lastName'] ?? null,
                'birth_date' => $validated['birthDate'] ?? null,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'avatar_url' => $avatarPath
            ]);
            if (!empty($newPassword)) {
                $hashedPassword = $this->hashPassword($newPassword);
                $repo->updateUserPassword($email, $hashedPassword);
            }
            $sportsRepo->setFavouriteSports($userId, $favouriteSports);
            if (!empty($avatarPath)) {
                $_SESSION['user_avatar'] = $avatarPath;
            }
        } catch (Throwable $e) {
            error_log("Profile update error: " . $e->getMessage());
            $this->setStatusCode(500);
            $this->render('profile', ['messages' => 'Failed to update profile']);
            return;
        }

        header('Location: /profile', true, 303);
    }
    
    public function editUser($userId = null) {
        $this->ensureSession();
        $editUserId = (int)($_GET['id'] ?? $userId ?? 0);
        if (!$editUserId) {
            $this->respondBadRequest();
        }
        if ($this->isPost()) {
            $ok = $this->handleUserEdit($editUserId, false);
            if ($ok) {
                header('Location: /joined', true, 303);
                exit();
            }
            return;
        }
        $repo = new UserRepository();
        $sportsRepo = new SportsRepository();
        $userRow = $repo->getUserById($editUserId);
        if (!$userRow) {
            $this->respondNotFound();
        }
        $userEntity = new \User($userRow);
        $user = [
            'email' => $userEntity->getEmail(),
            'firstName' => $userEntity->getFirstname() ?? '',
            'lastName' => $userEntity->getLastname() ?? '',
            'birthDate' => $userEntity->getBirthDate() ?? '',
            'latitude' => $userEntity->getLatitude(),
            'longitude' => $userEntity->getLongitude(),
            'avatar' => $userEntity->getAvatarUrl() ?? DEFAULT_AVATAR,
            'role' => $userEntity->getRole() ?? 'user'
        ];
        if (empty($user['location']) && !empty($user['latitude']) && !empty($user['longitude'])) {
            $user['location'] = $user['latitude'] . ', ' . $user['longitude'];
        }
        $allSports = $sportsRepo->getAllSports();
        $selectedSportIds = $sportsRepo->getFavouriteSportsIds($editUserId);
        $this->render('user-edit', [
            'user' => $user,
            'allSports' => $allSports,
            'selectedSportIds' => $selectedSportIds,
            'pageTitle' => 'My Profile'
        ]);
    }


    public function editUserByAdmin($userId = null) {
        $this->requireRole('admin');
        $userId = (int)($_GET['id'] ?? $userId ?? 0);
        if (!$userId) {
            $this->respondBadRequest();
        }
        if ($this->isPost()) {
            $ok = $this->handleUserEdit($userId, true);
            if ($ok) {
                if ($userId === $this->getCurrentUserId()) {
                    header('Location: /profile', true, 303);
                } else {
                    header('Location: /accounts/edit/' . $userId, true, 303);
                }
                exit();
            }
            return;
        }
        $repo = new UserRepository();
        $sportsRepo = new SportsRepository();
        $user = $repo->getUserById($userId);
        if (!$user) {
            $this->respondNotFound();
        }
        $user['firstName'] = $user['firstname'] ?? '';
        $user['lastName'] = $user['lastname'] ?? '';
        $user['birthDate'] = $user['birth_date'] ?? '';
        if (!empty($user['latitude']) && !empty($user['longitude'])) {
            $user['location'] = $user['latitude'] . ', ' . $user['longitude'];
        } else {
            $user['location'] = '';
        }
        $allSports = $sportsRepo->getAllSports();
        $selectedSportIds = $sportsRepo->getFavouriteSportsIds($userId);
        $this->render('admin/edit-user', [
            'user' => $user,
            'allSports' => $allSports,
            'selectedSportIds' => $selectedSportIds,
            'pageTitle' => 'Edit User - Admin'
        ]);
    }

     private function handleAvatarUpload(): ?string {
            if (isset($_FILES['avatar']) && isset($_FILES['avatar']['tmp_name']) && is_uploaded_file($_FILES['avatar']['tmp_name'])) {
                $file = $_FILES['avatar'];
                if ($file['error'] === UPLOAD_ERR_OK) {
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime = $finfo ? $finfo->file($file['tmp_name']) : '';
                    unset($finfo);
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
                            return '/public/images/avatars/' . $fileName;
                        }
                    }
                }
            }
            return null;
        }

        // Mapowanie danych z formularza na tablicę do updateUser
        private function mapUserUpdateData(array $validated, $latitude, $longitude, $avatarUrl = null): array {
            $data = [
                'firstname' => $validated['firstName'],
                'lastname' => $validated['lastName'],
                'birth_date' => $validated['birthDate'] ?: null
            ];
            if ($latitude !== null && $longitude !== null) {
                $data['latitude'] = $latitude;
                $data['longitude'] = $longitude;
            }
            if ($avatarUrl) {
                $data['avatar_url'] = $avatarUrl;
            }
            return $data;
        }
    // Wspólna logika edycji użytkownika (dla admina i ownera)
    private function handleUserEdit($userId, $isAdminEdit = false) {
        $repo = new UserRepository();
        $sportsRepo = new SportsRepository();
        $userRow = $repo->getUserById($userId);
        if (!$userRow) {
            $this->respondNotFound();
            return false;
        }
        // keep $user array shape for existing logic
        $user = $userRow;
        $validation = UserFormValidator::validate($_POST);
        $errors = $validation['errors'] ?? [];
        // Prefer DTO if available
        if (!empty($validation['dto']) && $validation['dto'] instanceof \UpdateUserDTO) {
            $validated = $validation['dto']->toArray();
        } else {
            $validated = $validation['data'] ?? [];
        }
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
        $avatarUrl = $this->handleAvatarUpload();
        $updateData = $this->mapUserUpdateData($validated, $latitude, $longitude, $avatarUrl);
        if (!empty($errors)) {
            $user['firstName'] = $validated['firstName'] ?? ($user['firstname'] ?? '');
            $user['lastName'] = $validated['lastName'] ?? ($user['lastname'] ?? '');
            $user['birthDate'] = $validated['birthDate'] ?? ($user['birth_date'] ?? '');
            if (!empty($user['latitude']) && !empty($user['longitude'])) {
                $user['location'] = $user['latitude'] . ', ' . $user['longitude'];
            } else {
                $user['location'] = '';
            }
            $allSports = $sportsRepo->getAllSports();
            $selectedSportIds = $sportsRepo->getFavouriteSportsIds($userId);
            $view = $isAdminEdit ? 'admin/edit-user' : 'user-edit';
            $this->render($view, [
                'user' => $user,
                'allSports' => $allSports,
                'selectedSportIds' => $selectedSportIds,
                'pageTitle' => $isAdminEdit ? 'Edit User - Admin' : 'My Profile',
                'messages' => implode('<br>', $errors)
            ]);
            return false;
        }
        $repo->updateUser($user['email'], $updateData);
        $favouriteSports = array_map('intval', $_POST['favourite_sports'] ?? []);
        $sportsRepo->setFavouriteSports($userId, $favouriteSports);
        if (!empty($validated['newPassword'])) {
            $hashedPassword = $this->hashPassword($validated['newPassword']);
            $repo->updateUserPassword($user['email'], $hashedPassword);
        }
        return true;
    }
}