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

        $targetId = $this->getTargetUserId($id);
        $dbUser = $this->userRepository->getUserProfileById($targetId);

        if (!$dbUser) {
            return $this->redirect('/dashboard');
        }

        $this->render('profile', array_merge(
            $this->prepareProfileViewData($dbUser),
            ['isOwnProfile' => $this->isOwnProfile($targetId)]
        ));
    }

    public function updateProfile(): void {
        $this->requireAuth();

        $targetId = $this->getTargetUserId();
        $user = $this->userRepository->getUserProfileById($targetId);

        if (!$user) {
            $this->redirect('/dashboard');
            return;
        }

        $passwordHash = null;
        if ($this->isOwnProfile($targetId)) {
            $authUser = $this->userRepository->getUserForAuthByEmail($user['email']);
            $passwordHash = $authUser['password'] ?? null;
        }

        $validation = UserFormValidator::validate($_POST, $passwordHash, $this->isOwnProfile($targetId));

        if (!empty($validation['errors'])) {
            $this->renderProfileWithErrors($user, $validation['errors']);
            return;
        }

        $location = $this->parseLocation($_POST['location'] ?? '');
        $avatarPath = $this->handleAvatarUpload($user['avatar_url']);

        $this->updateUserInfo($user, $validation['data'], $location, $avatarPath);
        $this->updateUserPasswordIfNeeded($user, $validation['data']);
        $this->updateFavouriteSports($targetId, $_POST['favourite_sports'] ?? []);

        $this->finalizeUpdate($_POST['context'] ?? 'user_profile');
    }

    private function getTargetUserId(?int $id = null): int {
        if ($this->isAdmin() && $id !== null) {
            return (int)$id;
        }
        if ($this->isAdmin() && isset($_POST['id'])) {
            return (int)$_POST['id'];
        }
        return $this->getCurrentUserId();
    }

    private function isOwnProfile(int $targetId): bool {
        return $targetId === $this->getCurrentUserId();
    }

    private function updateUserInfo(array $existingUser, array $validated, array $location, string $avatarPath): void {
        $updateData = [
            'firstname'  => $validated['firstName'],
            'lastname'   => $validated['lastName'],
            'birth_date' => $validated['birthDate'] ?: null,
            'latitude'   => $location['lat'] ?? null,
            'longitude'  => $location['lng'] ?? null,
            'avatar_url' => $avatarPath,
            'role'       => $existingUser['role'],
            'enabled'    => $existingUser['enabled'] ? 'true' : 'false'
        ];

        if ($this->isAdmin()) {
            if (isset($_POST['role'])) $updateData['role'] = $_POST['role'];
            $updateData['enabled'] = isset($_POST['enabled']) ? 'true' : 'false';
        }

        $this->userRepository->updateUser($existingUser['email'], $updateData);
    }

    private function updateUserPasswordIfNeeded(array $existingUser, array $validated): void {
        if (!empty($validated['newPassword'])) {
            $hashed = password_hash($validated['newPassword'], PASSWORD_BCRYPT);
            $this->userRepository->updateUserPassword($existingUser['email'], $hashed);
        }
    }

    private function updateFavouriteSports(int $userId, array $sportIds): void {
        $this->sportsRepository->setFavouriteSports($userId, array_map('intval', $sportIds));
    }

    private function handleAvatarUpload(?string $existingAvatar = null): string {
        $default = $existingAvatar ?: '/public/img/default-avatar.png';

        if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
            return $default;
        }

        $targetDir = __DIR__ . '/../../public/images/avatars';
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

        $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
        $fileName = 'avatar_' . bin2hex(random_bytes(8)) . '.' . $ext;

        if (move_uploaded_file($_FILES['avatar']['tmp_name'], "$targetDir/$fileName")) {
            return '/public/images/avatars/' . $fileName;
        }

        return $default;
    }

    private function parseLocation(string $location): array {
        if (str_contains($location, ',')) {
            [$lat, $lng] = array_map('trim', explode(',', $location));
            if (is_numeric($lat) && is_numeric($lng)) {
                return ['lat' => (float)$lat, 'lng' => (float)$lng];
            }
        }
        return ['lat' => null, 'lng' => null];
    }

    private function prepareProfileViewData(array $dbUser): array {
        $userId = (int)$dbUser['id'];

        return [
            'pageTitle' => 'Edycja Profilu',
            'user' => [
                'id' => $userId,
                'firstName' => $dbUser['firstname'] ?? '',
                'lastName' => $dbUser['lastname'] ?? '',
                'email' => $dbUser['email'] ?? '',
                'birthDate' => $dbUser['birth_date'] ?? '',
                'location' => ($dbUser['latitude'] && $dbUser['longitude'] ? "{$dbUser['latitude']}, {$dbUser['longitude']}" : ''),
                'avatar' => $dbUser['avatar_url'] ?: '/public/img/default-avatar.png',
                'role' => $dbUser['role'] ?? 'user',
                'enabled' => $dbUser['enabled'] ?? true,
                'statistics' => $this->userRepository->getUserStatisticsById($userId),
            ],
            'allSports' => $this->sportsRepository->getAllSports(),
            'selectedSportIds' => $this->sportsRepository->getFavouriteSportsIds($userId)
        ];
    }

    private function renderProfileWithErrors(array $user, array $errors): void {
        $this->render('profile', array_merge(
            $this->prepareProfileViewData($user),
            ['messages' => implode('<br>', $errors), 'isOwnProfile' => $this->isOwnProfile((int)$user['id'])]
        ));
    }

    private function finalizeUpdate(string $context): void {
        $path = ($context === 'admin_panel') ? '/accounts?success=1' : '/profile?success=1';
        header("Location: $path");
        exit();
    }
}
