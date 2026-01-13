<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repository/EventRepository.php';

class EventController extends AppController {

    public function details($id = null, $action = null) {
        $repo = new EventRepository();
        $row = $repo->getEventById((int)$id);
        $event = null;
        $userId = $this->getCurrentUserId();
        if ($row) {
            $event = [
                'id' => (int)$row['id'],
                'title' => (string)$row['title'],
                'location' => (string)($row['location_text'] ?? ''),
                'coords' => isset($row['latitude'],$row['longitude']) ? ($row['latitude'] . ', ' . $row['longitude']) : null,
                'dateTime' => (new DateTime($row['start_time']))->format('D, M j, g:i A'),
                'skillLevel' => (string)($row['level_name'] ?? 'Intermediate'),
                'organizer' => [
                    'name' => (trim($row['firstname'] ?? '') . ' ' . trim($row['lastname'] ?? '')) ?: 'Organizer',
                    'avatar' => (string)($row['avatar_url'] ?? '')
                ],
                'participants' => [
                    'current' => (int)($row['current_players'] ?? 0),
                    'max' => (int)($row['max_players'] ?? 0),
                    'minNeeded' => (int)($row['min_needed'] ?? 0)
                ]
            ];
            if ($userId) {
                $isOwner = (int)$row['owner_id'] === (int)$userId;
                $event['isUserParticipant'] = $isOwner || $repo->isUserParticipant($userId, (int)$id);
                $event['isOwner'] = $isOwner;
                $event['isFull'] = $repo->isEventFull((int)$id);
            }
        }
        $this->render('event', [
            'pageTitle' => 'SportMatch - Match Details',
            'activeNav' => 'sports',
            'event' => $event ?? [
                'id' => (int)$id,
                'title' => 'Match Details',
                'location' => 'Unknown',
                'coords' => null,
                'dateTime' => 'TBD',
                'skillLevel' => 'Intermediate',
                'organizer' => [
                    'name' => 'Organizer',
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

        $repo = new EventRepository();
        if ($repo->isEventPast((int)$id)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Event has already passed']);
            return;
        }
        if ($repo->isEventFull((int)$id)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Event is full']);
            return;
        }
        $result = $repo->joinEventWithTransaction($userId, (int)$id);
        
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
        $this->ensureSession();
        $userId = $this->getCurrentUserId();
        
        if (!$userId) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
            exit();
        }

        // Get event to check ownership
        $repo = new EventRepository();
        $event = $repo->getEventById((int)$id);
        
        if (!$event) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Event not found']);
            exit();
        }
        
        // Check if owner or admin - if so, delete event instead
        $isOwner = isset($event['owner_id']) && ((int)$event['owner_id'] === (int)$userId);
        $isAdmin = $this->isAdmin();
        
        if ($isOwner || $isAdmin) {
            // Delete event
            $result = (new EventRepository())->deleteEvent((int)$id);
            
            if ($result) {
                http_response_code(200);
                echo json_encode(['status' => 'success', 'message' => 'Event deleted']);
            } else {
                http_response_code(500);
                echo json_encode(['status' => 'error', 'message' => 'Failed to delete event']);
            }
        } else {
            // Cancel participation - używamy transakcji
            $result = $repo->cancelParticipationWithTransaction($userId, (int)$id);
            
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
