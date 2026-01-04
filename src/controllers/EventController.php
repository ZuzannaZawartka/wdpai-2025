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

    public function join($id) {
        header('Content-Type: application/json');
        $userId = $this->getCurrentUserId();
        if (!$userId) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
            return;
        }

        $result = MockRepository::joinEvent($userId, (int)$id);
        
        if ($result) {
            http_response_code(200);
            echo json_encode(['status' => 'success', 'message' => 'Joined event']);
        } else {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Failed to join event']);
        }
    }

    public function cancel($id) {
        header('Content-Type: application/json');
        $userId = $this->getCurrentUserId();
        if (!$userId) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
            return;
        }

        $result = MockRepository::cancelEventParticipation($userId, (int)$id);
        
        if ($result) {
            http_response_code(200);
            echo json_encode(['status' => 'success', 'message' => 'Cancelled participation']);
        } else {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Failed to cancel participation']);
        }
    }
}
