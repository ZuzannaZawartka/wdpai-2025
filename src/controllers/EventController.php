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
                'description' => (string)($row['description'] ?? ''),
                'sportName' => (string)($row['sport_name'] ?? ''),
                'location' => (string)($row['location_text'] ?? ''),
                'coords' => isset($row['latitude'],$row['longitude']) ? ($row['latitude'] . ', ' . $row['longitude']) : null,
                'dateTime' => (new DateTime($row['start_time']))->format('D, M j, g:i A'),
                'skillLevel' => (string)($row['level_name'] ?? 'Intermediate'),
                'organizer' => [
                    'name' => (trim($row['firstname'] ?? '') . ' ' . trim($row['lastname'] ?? '')) ?: 'Organizer',
                    'email' => (string)($row['owner_email'] ?? ''),
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
                'description' => '',
                'sportName' => '',
                'location' => 'Unknown',
                'coords' => null,
                'dateTime' => 'TBD',
                'skillLevel' => 'Intermediate',
                'organizer' => [
                    'name' => 'Organizer',
                    'email' => '',
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

    public function delete($id) {
        header('Content-Type: application/json');
        $this->ensureSession();
        $userId = $this->getCurrentUserId();
        if (!$userId) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
            exit();
        }
        $repo = new EventRepository();
        $event = $repo->getEventById((int)$id);
        if (!$event) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Event not found']);
            exit();
        }
        $isOwner = isset($event['owner_id']) && ((int)$event['owner_id'] === (int)$userId);
        $isAdmin = $this->isAdmin();
        $repo = new EventRepository();
        if ($isOwner) {
            $result = $repo->deleteEventByOwner((int)$id, (int)$userId);
        } elseif ($isAdmin) {
            $result = $repo->deleteEvent((int)$id);
        } else {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Only owner or admin can delete event']);
            exit();
        }
        if ($result) {
            http_response_code(200);
            echo json_encode(['status' => 'success', 'message' => 'Event deleted']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to delete event']);
        }
        exit();
    }

    public function leave($id) {
        header('Content-Type: application/json');
        $this->ensureSession();
        $userId = $this->getCurrentUserId();
        if (!$userId) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
            exit();
        }
        $repo = new EventRepository();
        $event = $repo->getEventById((int)$id);
        if (!$event) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Event not found']);
            exit();
        }
        // Nie pozwól ownerowi opuścić własnego eventu (może tylko usunąć)
        if ((int)$event['owner_id'] === (int)$userId) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Owner cannot leave their own event']);
            exit();
        }
        $result = $repo->cancelParticipationWithTransaction($userId, (int)$id);
        if ($result) {
            http_response_code(200);
            echo json_encode(['status' => 'success', 'message' => 'Left event']);
        } else {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Failed to leave event']);
        }
        exit();
    }
}
