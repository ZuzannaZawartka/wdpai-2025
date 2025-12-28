<?php

require_once 'AppController.php';

class EventController extends AppController {

    public function details($id) {
        $this->render('event', [
            'pageTitle' => 'SportMatch - Match Details',

            'activeNav' => 'sports',
            'event' => [
                'id' => $id,
                'title' => 'Afternoon Football Kickabout',
                
                'location' => 'Central Park, New York, NY',
               
                'coords' => '40.7829, -73.9654',
                'dateTime' => 'Saturday, October 28, 2023 at 2:00 PM',
                'skillLevel' => 'Intermediate',
                'organizer' => [
                    'name' => 'John Doe',
                    'avatar' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuBAScERWuqlLWv7xDKAkq0vfJxFdtSjQSK-FBJ1endTel7Mo7aFi0qPk4gxnCNCb1jcOw5bTCZJiVDLAQBhlpBj8U1-gi_i4oGP1rXh5zh8C2QBXlIswW7gAzNq7lyqcg86rnO-VrLXkVrwY09NH7MSdq5pIlpsLh7-jmorWShz4aYci8ypqMqV2CSd1MG9qj87bzPvc2crbK3BFhR1PbJfg5TBMCDu4WMtUeUa_ztoq4KYvLtAfafiy448mlV76OCmMn4-TgmdbJx8'
                ],
                'participants' => [
                    'current' => 8,
                    'max' => 12,
                    'minNeeded' => 6
                ],
                'mapImage' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuDY7fU7p4KngcIE_YppKnTKRCyU2rpdvc4VBFGfX9nAXbHlUYmhdqPTT7XhwTpXDrxBEzWYrPLgvQSM42DfRNlNkWKxbB9vUMgxwf0cEoMWYRWhkPS9h5vkHGjAFJO092xQ_9F67k8qFYz1Z5Jd9U7_4CISUKIh7OufN04-R5XkVlc_R2PAOtUfjQhi1Ve5fnD9PsekGBhYC85uB62Um5_S4V5IdCiE1a-NyAsqNK0YcU0ipKXQf2zvbxxqWpM8Dsw739o6yYzUfItE'
            ]
        ]);
    }
}
