<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repository/MockRepository.php';

class EventController extends AppController {

    public function details($id = null, $action = null) {
        $mock = MockRepository::getEventById((int)$id);
        
        if ($mock && $this->isAdmin()) {
            $mock['isUserParticipant'] = true;
        }
        
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
            // Check specific failure reasons
            if (MockRepository::isEventPast((int)$id)) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Event has already passed']);
            } elseif (MockRepository::isEventFull((int)$id)) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Event is full']);
            } else {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Failed to join event']);
            }
        }
    }

    public function cancel($id) {
        header('Content-Type: application/json');
        $this->ensureSession();
        $userId = $this->getCurrentUserId();
        
        if (!$userId) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
            exit();
        }

        // Get event to check ownership
        $event = MockRepository::getEventById((int)$id);
        
        if (!$event) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Event not found']);
            exit();
        }
        
        // Check if owner or admin - if so, delete event instead
        $isOwner = ($event['ownerId'] ?? null) === $userId;
        $isAdmin = $this->isAdmin();
        
        if ($isOwner || $isAdmin) {
            // Delete event
            $result = MockRepository::deleteEvent((int)$id);
            
            if ($result) {
                http_response_code(200);
                echo json_encode(['status' => 'success', 'message' => 'Event deleted']);
            } else {
                http_response_code(500);
                echo json_encode(['status' => 'error', 'message' => 'Failed to delete event']);
            }
        } else {
            // Cancel participation
            $result = MockRepository::cancelEventParticipation($userId, (int)$id);
            
            if ($result) {
                http_response_code(200);
                echo json_encode(['status' => 'success', 'message' => 'Cancelled participation']);
            } else {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Failed to cancel participation']);
            }
        }
        exit();
    }
}
