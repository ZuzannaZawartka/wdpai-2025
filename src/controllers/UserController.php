<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repository/UserRepository.php';
require_once __DIR__ . '/../repository/SportsRepository.php';
require_once __DIR__ . '/../validators/UserFormValidator.php';

class UserController extends AppController {
    private UserRepository $userRepository;
    private SportsRepository $sportsRepository;

    public function __construct() {
        parent::__construct();
        $this->userRepository = new UserRepository();
        $this->sportsRepository = new SportsRepository();
    }

    public function profile($id = null) {
        $this->requireAuth();

        if ($this->isPost()) {
            return $this->updateProfile();
        }

        $targetId = ($id && $this->isAdmin()) ? (int)$id : $this->getCurrentUserId();
        $dbUser = $this->userRepository->getUserById($targetId);

        if (!$dbUser) {
            return $this->redirect('/dashboard');
        }

        $this->render("profile", array_merge(
            $this->prepareProfileViewData($dbUser),
            ['isOwnProfile' => ($targetId === $this->getCurrentUserId())]
        ));
    }

    public function updateProfile(): void {
        $this->requireAuth();
        
        $targetId = $this->resolveTargetId();
        $existingUser = $this->userRepository->getUserById($targetId);
        
        if (!$existingUser) {
            $this->redirect('/dashboard');
            return;
        }

        $isOwnProfile = ($targetId === $this->getCurrentUserId());

        $validation = UserFormValidator::validate($_POST, $existingUser['password'], $isOwnProfile);
        
        if (!empty($validation['errors'])) {
            $this->handleValidationError($existingUser, $validation['errors'], $isOwnProfile);
            return;
        }

        try {
            $this->saveUserData($existingUser, $validation['data'], $targetId);
            
            $this->finalizeUpdate($_POST['context'] ?? 'user_profile');
        } catch (Throwable $e) {
            error_log("Błąd UserController: " . $e->getMessage());
            $this->render('profile', ['messages' => 'Błąd zapisu danych w bazie.']);
        }
    }

    private function saveUserData(array $existingUser, array $validated, int $targetId): void {
        [$lat, $lng] = $this->parseLocation($_POST['location'] ?? '');
        $avatarPath = $this->handleAvatarUpload() ?: $existingUser['avatar_url'];

        $updateData = [
            'firstname'  => $validated['firstName'],
            'lastname'   => $validated['lastName'],
            'birth_date' => $validated['birthDate'] ?: null,
            'latitude'   => $lat,
            'longitude'  => $lng,
            'avatar_url' => $avatarPath,
            'role'       => $existingUser['role'],
            'enabled'    => $existingUser['enabled'] ? 'true' : 'false'
        ];
        if ($this->isAdmin()) {
            if (isset($_POST['role'])) $updateData['role'] = $_POST['role'];
            $updateData['enabled'] = isset($_POST['enabled']) ? 'true' : 'false';
        }

        $this->userRepository->updateUser($existingUser['email'], $updateData);

        if (!empty($validated['newPassword'])) {
            $hashed = password_hash($validated['newPassword'], PASSWORD_BCRYPT);
            $this->userRepository->updateUserPassword($existingUser['email'], $hashed);
        }

        $sportIds = array_map('intval', $_POST['favourite_sports'] ?? []);
        $this->sportsRepository->setFavouriteSports($targetId, $sportIds);
    }

    private function resolveTargetId(): int {
        return ($this->isAdmin() && isset($_POST['id'])) ? (int)$_POST['id'] : $this->getCurrentUserId();
    }

    private function handleValidationError(array $user, array $errors, bool $isOwnProfile): void {
        $this->render('profile', array_merge(
            $this->prepareProfileViewData($user, $_POST['location'] ?? ''),
            [
                'messages' => implode('<br>', $errors),
                'isOwnProfile' => $isOwnProfile
            ]
        ));
    }

    private function handleAvatarUpload(): ?string {
        if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
            return null;
        }

        $targetDir = __DIR__ . '/../../public/images/avatars';
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

        $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
        $fileName = 'avatar_' . bin2hex(random_bytes(8)) . '.' . $ext;
        
        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $targetDir . '/' . $fileName)) {
            return '/public/images/avatars/' . $fileName;
        }
        return null;
    }

    private function prepareProfileViewData(array $dbUser, string $overrideLoc = null): array {
        $userId = (int)$dbUser['id'];
        return [
            'pageTitle' => 'Edycja Profilu',
            'user' => [
                'id' => $userId,
                'firstName' => $dbUser['firstname'] ?? '',
                'lastName' => $dbUser['lastname'] ?? '',
                'email' => $dbUser['email'] ?? '',
                'birthDate' => $dbUser['birth_date'] ?? '',
                'location' => $overrideLoc ?: ($dbUser['latitude'] && $dbUser['longitude'] ? "{$dbUser['latitude']}, {$dbUser['longitude']}" : ''),
                'avatar' => $dbUser['avatar_url'] ?: '/public/img/default-avatar.png',
                'role' => $dbUser['role'] ?? 'user',
                'enabled' => $dbUser['enabled'] ?? true,
                'statistics' => $this->userRepository->getUserStatisticsById($userId),
            ],
            'allSports' => $this->sportsRepository->getAllSports(),
            'selectedSportIds' => $this->sportsRepository->getFavouriteSportsIds($userId)
        ];
    }

    private function parseLocation(string $location): array {
        if (str_contains($location, ',')) {
            $parts = array_map('trim', explode(',', $location));
            if (count($parts) === 2 && is_numeric($parts[0]) && is_numeric($parts[1])) {
                return [(float)$parts[0], (float)$parts[1]];
            }
        }
        return [null, null];
    }

    private function finalizeUpdate(string $context): void {
        $path = ($context === 'admin_panel') ? '/accounts?success=1' : '/profile?success=1';
        header("Location: $path");
        exit();
    }
}