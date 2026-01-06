<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repository/MockRepository.php';
require_once __DIR__ . '/../repository/UserRepository.php';

class JoinedController extends AppController {

    public function index(): void
    {
        $this->ensureSession();
        $userId = $this->getCurrentUserId();
        
        // If admin, show accounts instead
        if ($this->isAdmin()) {
            $this->adminAccounts();
            return;
        }
        
        $joinedMatches = MockRepository::joinedMatches($userId);

        $this->render('joined', [
            'pageTitle' => 'SportMatch - Joined Events',
            'activeNav' => 'joined',
            'joinedMatches' => $joinedMatches,
        ]);
    }
    
    private function adminAccounts(): void {
        $userRepo = new UserRepository();
        $users = $userRepo->getUsers();
        
        $this->render('accounts', [
            'pageTitle' => 'SportMatch - Accounts',
            'activeNav' => 'joined',
            'accounts' => $users,
        ]);
    }
}
