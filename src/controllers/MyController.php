<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repository/MockRepository.php';

class MyController extends AppController {

    public function index(): void
    {
        $myEvents = MockRepository::myEvents();

        $this->render('my', [
            'pageTitle' => 'SportMatch - My Events',
            'activeNav' => 'my',
            'myEvents' => $myEvents,
        ]);
    }
}
