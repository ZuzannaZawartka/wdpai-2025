<?php

require_once 'AppController.php';
require_once 'UserController.php'; // Musisz zaimportować UserController
require_once __DIR__ . '/../repository/UserRepository.php';
require_once __DIR__ . '/../repository/EventRepository.php';
require_once __DIR__ . '/../repository/SportsRepository.php';
require_once __DIR__ . '/../validators/UserFormValidator.php';
require_once __DIR__ . '/../entity/User.php';

class AdminController extends AppController
{

    private UserRepository $userRepository;
    private EventRepository $eventRepository;

    public function __construct()
    {
        parent::__construct();
        $this->userRepository = new UserRepository();
        $this->eventRepository = new EventRepository();
    }

    public function accounts()
    {
        $this->requireRole('admin');

        $allUserEntities = $this->userRepository->getUsersEntities();
        $stats = $this->userRepository->getUsersStatistics();
        $statsById = [];
        foreach ($stats as $stat) {
            $statsById[(int)$stat['id']] = $stat;
        }
        $usersOut = array_map(function (\User $u) use ($statsById) {
            $uid = (int)($u->getId() ?? 0);
            $joined = $statsById[$uid]['events_joined_count'] ?? 0;
            $created = $statsById[$uid]['events_created_count'] ?? 0;
            return [
                'id' => $u->getId(),
                'firstname' => $u->getFirstname(),
                'lastname' => $u->getLastname(),
                'email' => $u->getEmail(),
                'role' => $u->getRole(),
                'birth_date' => $u->getBirthDate(),
                'latitude' => $u->getLatitude(),
                'longitude' => $u->getLongitude(),
                'avatar_url' => $u->getAvatarUrl(),
                'events_joined_count' => $joined,
                'events_created_count' => $created,
            ];
        }, $allUserEntities);

        return $this->render('admin/accounts', [
            'users' => $usersOut,
            'pageTitle' => 'Manage Users - Admin'
        ]);
    }

    public function editUser($id)
    {
        $this->requireRole('admin');
        $userController = new UserController();
        return $userController->editUser($id);
    }
}
