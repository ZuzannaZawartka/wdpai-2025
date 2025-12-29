<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repository/MockRepository.php';

class EventController extends AppController {

    public function details($id) {
        $mock = MockRepository::getEventById((int)$id);
        $this->render('event', [
            'pageTitle' => 'SportMatch - Match Details',
            'activeNav' => 'sports',
            'event' => $mock ?? [
                'id' => (int)$id,
                'title' => 'Match Details',
                'location' => 'Unknown',
                'coords' => null,
                'dateTime' => 'TBD',
                'skillLevel' => 'Intermediate',
                'organizer' => [
                    'name' => 'John Doe',
                    'avatar' => ''
                ],
                'participants' => [
                    'current' => 0,
                    'max' => 0,
                    'minNeeded' => 0
                ]
            ]
        ]);
    }
}
