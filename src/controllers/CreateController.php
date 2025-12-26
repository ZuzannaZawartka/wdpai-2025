<?php

require_once 'AppController.php';

class CreateController extends AppController {

    public function index(): void
    {
        // Render empty form; future: handle POST to persist event
        $skillLevels = ['Advanced', 'Intermediate', 'Beginner', 'For Fun', 'Just Started'];
        $this->render('create', [
            'pageTitle' => 'SportMatch - Create Event',
            'activeNav' => 'create',
            'skillLevels' => $skillLevels,
        ]);
    }
}
