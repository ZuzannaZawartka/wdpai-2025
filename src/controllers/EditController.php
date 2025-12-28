<?php

require_once 'AppController.php';

class EditController extends AppController {

    public function edit($id): void
    {
        $skillLevels = ['Advanced', 'Intermediate', 'Beginner', 'For Fun', 'Just Started'];
        $event = [
            'id' => (int)$id,
            'title' => 'Afternoon Football Kickabout',
            'datetime' => '2025-01-15T18:00',
            'skillLevel' => 'Intermediate',
            'location' => '40.78290, -73.96540',
            'participants' => [
                'type' => 'range',
                'specific' => null,
                'minimum' => 6,
                'rangeMin' => 6,
                'rangeMax' => 12
            ]
        ];

        $this->render('edit', [
            'pageTitle' => 'SportMatch - Edit Event',
            'activeNav' => 'edit',
            'skillLevels' => $skillLevels,
            'event' => $event,
        ]);
    }
}
