<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repository/UserRepository.php';
require_once __DIR__ . '/../repository/SportsRepository.php';
require_once __DIR__ . '/../validators/UserFormValidator.php';
require_once __DIR__ . '/../entity/User.php';
require_once __DIR__ . '/../config/AppConfig.php';

class UserController extends AppController
{

    private UserRepository $userRepository;
    private SportsRepository $sportsRepository;

    protected function __construct()
    {
        parent::__construct();
        $this->userRepository = UserRepository::getInstance();
        $this->sportsRepository = SportsRepository::getInstance();
    }


    /**
     * Handles user profile editing
     * Both own profile and admin editing other users
     * 
     * @param int|null $userId User ID to edit (null for own profile)
     */
    public function editUser($userId = null)
    {
        $this->ensureSession();

        $targetId = $this->getTargetUserId($userId);
        $userProfile = $this->userRepository->getUserProfileById($targetId);

        if (!$userProfile) {
            $this->redirect('/dashboard');
        }

        $passwordHash = null;
        if ($this->isOwnProfile($targetId)) {
            $authUser = $this->userRepository->getUserForAuthByEmail($userProfile['email']);
            $passwordHash = $authUser['password'] ?? null;
        }

        if ($this->isPost()) {
            $validation = UserFormValidator::validate($_POST, $passwordHash, $this->isOwnProfile($targetId));

            if (!empty($validation['errors'])) {
                $this->renderProfileWithErrors($userProfile, $validation['errors']);
                return;
            }


            $dto = $validation['dto'] ?? null;
            $validatedData = ($dto instanceof \UpdateUserDTO) ? $dto->toArray() : $validation['data'];

            $location = $this->parseLocation($_POST['location'] ?? '');
            $avatarPath = $this->handleAvatarUpload($userProfile['avatar_url']);

            $this->updateUserInfo($userProfile, $validatedData, $location, $avatarPath);
            $this->updateUserPasswordIfNeeded($userProfile, $validatedData);
            $this->updateFavouriteSports($targetId, $_POST['favourite_sports'] ?? []);

            $this->finalizeUpdate($_POST['context'] ?? 'user_profile');
            return;
        }


        $this->render('profile', array_merge(
            $this->prepareProfileViewData($userProfile),
            ['isOwnProfile' => $this->isOwnProfile($targetId)]
        ));
    }

    /**
     * Gets target user ID for editing
     * Admin can edit any user, regular users only themselves
     * 
     * @param int|null $id User ID from parameter
     * @return int Target user ID
     */
    private function getTargetUserId(?int $id = null): int
    {
        if ($this->isAdmin() && $id !== null) {
            return (int)$id;
        }
        if ($this->isAdmin() && isset($_POST['id'])) {
            return (int)$_POST['id'];
        }
        return $this->getCurrentUserId();
    }

    /**
     * Checks if target profile belongs to current user
     * 
     * @param int $targetId Target user ID
     * @return bool true if it's user's own profile
     */
    private function isOwnProfile(int $targetId): bool
    {
        return $targetId === $this->getCurrentUserId();
    }

    /**
     * Updates user information in database
     * 
     * @param array $existingUser Existing user data
     * @param array $validated Validated form data
     * @param array $location Location coordinates
     * @param string $avatarPath Avatar file path
     */
    private function updateUserInfo(array $existingUser, array $validated, array $location, string $avatarPath): void
    {
        $updateData = [
            'firstname'  => $validated['firstName'],
            'lastname'   => $validated['lastName'],
            'birth_date' => $validated['birthDate'] ?: null,
            'latitude'   => $location['lat'] ?? $existingUser['latitude'],
            'longitude'  => $location['lng'] ?? $existingUser['longitude'],
            'avatar_url' => $avatarPath,
            'role'       => $existingUser['role'],
            'enabled'    => $existingUser['enabled'] ? 'true' : 'false'
        ];

        if ($this->isAdmin()) {
            if (isset($_POST['role'])) $updateData['role'] = $_POST['role'];
            $updateData['enabled'] = isset($_POST['enabled']) ? 'true' : 'false';
        }

        $success = $this->userRepository->updateUser($existingUser['email'], $updateData);


        if ($success && $this->isOwnProfile((int)$existingUser['id'])) {
            $_SESSION['user_avatar'] = $avatarPath;
        }
    }

    /**
     * Updates user password if new password provided
     * 
     * @param array $existingUser Existing user data
     * @param array $validated Validated form data
     */
    private function updateUserPasswordIfNeeded(array $existingUser, array $validated): void
    {
        if (!empty($validated['newPassword'])) {
            $hashed = password_hash($validated['newPassword'], PASSWORD_BCRYPT);
            $this->userRepository->updateUserPassword($existingUser['email'], $hashed);
        }
    }

    /**
     * Updates user's favorite sports
     * 
     * @param int $userId User ID
     * @param array $sportIds Array of sport IDs
     */
    private function updateFavouriteSports(int $userId, array $sportIds): void
    {
        $this->sportsRepository->setFavouriteSports($userId, array_map('intval', $sportIds));
    }

    /**
     * Handles avatar file upload
     * 
     * @param string|null $existingAvatar Current avatar path
     * @return string New avatar path or existing/default
     */
    private function handleAvatarUpload(?string $existingAvatar = null): string
    {
        require_once __DIR__ . '/../config/AppConfig.php';
        $default = $existingAvatar ?: AppConfig::DEFAULT_USER_AVATAR;

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

    /**
     * Parses location string to coordinates
     * 
     * @param string $location Location string (lat, lng)
     * @return array Array with 'lat' and 'lng' keys
     */
    private function parseLocation(string $location): array
    {
        if (str_contains($location, ',')) {
            [$lat, $lng] = array_map('trim', explode(',', $location));
            if (is_numeric($lat) && is_numeric($lng)) {
                return ['lat' => (float)$lat, 'lng' => (float)$lng];
            }
        }
        return ['lat' => null, 'lng' => null];
    }

    /**
     * Prepares profile view data
     * 
     * @param array $dbUser User data from database
     * @return array View data array
     */
    private function prepareProfileViewData(array $dbUser): array
    {
        $userId = (int)($dbUser['id'] ?? 0);
        $userEntity = new \User($dbUser);
        $isOwnProfile = $this->isOwnProfile($userId);

        return [
            'pageTitle' => $isOwnProfile ? 'Moja Profil' : 'Edycja Użytkownika',
            'headerTitle' => $isOwnProfile ? 'My Profile' : 'Edit User',
            'user' => [
                'id' => $userEntity->getId(),
                'firstName' => $userEntity->getFirstname() ?? '',
                'lastName' => $userEntity->getLastname() ?? '',
                'email' => $userEntity->getEmail() ?? '',
                'birthDate' => $userEntity->getBirthDate() ?? '',
                'location' => ($userEntity->getLatitude() && $userEntity->getLongitude() ? "{$userEntity->getLatitude()}, {$userEntity->getLongitude()}" : ''),
                'avatar' => $userEntity->getAvatarUrl() ?: AppConfig::DEFAULT_USER_AVATAR,
                'role' => $userEntity->getRole() ?? 'user',
                'enabled' => $dbUser['enabled'] ?? true,
                'statistics' => $this->userRepository->getUserStatisticsById($userId),
            ],
            'allSports' => $this->sportsRepository->getAllSports(),
            'selectedSportIds' => $this->sportsRepository->getFavouriteSportsIds($userId),
            'isOwnProfile' => $isOwnProfile,
            'isAdminViewer' => $this->isAdmin()
        ];
    }

    /**
     * Renders profile with validation errors
     * 
     * @param array $user User data
     * @param array $errors Validation errors
     */
    private function renderProfileWithErrors(array $user, array $errors): void
    {
        $this->render('profile', array_merge(
            $this->prepareProfileViewData($user),
            ['messages' => implode('<br>', $errors), 'isOwnProfile' => $this->isOwnProfile((int)$user['id'])]
        ));
    }

    /**
     * Redirects after successful update
     * 
     * @param string $context Context ('admin_panel' or 'user_profile')
     */
    private function finalizeUpdate(string $context): void
    {
        $path = ($context === 'admin_panel') ? '/accounts?success=1' : '/profile?success=1';
        $this->redirect($path);
    }
}
