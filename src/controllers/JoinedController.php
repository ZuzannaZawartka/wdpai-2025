<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repository/MockRepository.php';

class JoinedController extends AppController {

    public function index(): void
    {
        $joinedMatches = MockRepository::joinedMatches();

        $this->render('joined', [
            'pageTitle' => 'SportMatch - Joined Events',
            'activeNav' => 'joined',
            'joinedMatches' => $joinedMatches,
        ]);
    }
}
