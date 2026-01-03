<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repository/MockRepository.php';

class JoinedController extends AppController {

    public function index(): void
    {
        $this->ensureSession();
        $userId = $this->getCurrentUserId();
        
        $joinedMatches = MockRepository::joinedMatches($userId);

        $this->render('joined', [
            'pageTitle' => 'SportMatch - Joined Events',
            'activeNav' => 'joined',
            'joinedMatches' => $joinedMatches,
        ]);
    }
}
