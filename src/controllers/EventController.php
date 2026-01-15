<?php
require_once 'AppController.php';
require_once __DIR__ . '/../repository/EventRepository.php';
require_once __DIR__ . '/../repository/SportsRepository.php';
require_once __DIR__ . '/../validators/EventFormValidator.php';

class EventController extends AppController {
        public function showCreateForm(): void
        {
            // Admins cannot create events
            if ($this->isAdmin()) {
                header('HTTP/1.1 403 Forbidden');
                $this->render('404');
                return;
            }
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $this->createEvent();
            } else {
                $this->renderCreateForm();
            }
        }

        private function renderCreateForm(): void
        {
            $sportsRepo = new SportsRepository();
            $skillLevels = array_map(fn($l) => $l['name'], $sportsRepo->getAllLevels());
            $allSports = $sportsRepo->getAllSports();
            parent::render('create', [
                'pageTitle' => 'SportMatch - Create Event',
                'activeNav' => 'create',
                'skillLevels' => $skillLevels,
                'allSports' => $allSports,
            ]);
        }

        public function createEvent(): void
        {
            $this->ensureSession();
            $validation = EventFormValidator::validate($_POST);
            if (!empty($validation['errors'])) {
                $sportsRepo = new SportsRepository();
                $skillLevels = array_map(fn($l) => $l['name'], $sportsRepo->getAllLevels());
                $allSports = $sportsRepo->getAllSports();
                parent::render('create', [
                    'pageTitle' => 'SportMatch - Create Event',
                    'activeNav' => 'create',
                    'skillLevels' => $skillLevels,
                    'allSports' => $allSports,
                    'errors' => $validation['errors'],
                    'formData' => $_POST
                ]);
                return;
            }
            $ownerId = $this->getCurrentUserId();
            $imageUrl = null;
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $tmpName = $_FILES['image']['tmp_name'];
                $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                $fileName = 'event_' . uniqid() . '.' . $ext;
                $targetPath = __DIR__ . '/../../public/images/events/' . $fileName;
                if (move_uploaded_file($tmpName, $targetPath)) {
                    $imageUrl = '/public/images/events/' . $fileName;
                }
            }
            if (!$imageUrl) {
                $imageUrl = '/public/images/boisko.png';
            }
            $newEvent = array_merge($validation['data'], [
                'owner_id' => $ownerId,
                'image_url' => $imageUrl
            ]);
            $repo = new EventRepository();
            $eventId = $repo->createEvent($newEvent);
            if ($eventId) {
                header('Location: /event/' . $eventId);
                exit;
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to create event']);
            }
        }

    public function showEditForm($id): void
    {
        $this->ensureSession();
        $repo = new EventRepository();
        $row = $repo->getEventById((int)$id);
        if (!$row) {
            $this->render('404');
            return;
        }
        $isReadOnly = !$this->isAdmin() && $repo->isEventPast((int)$id);
        $sportsRepo = new SportsRepository();
        $skillLevels = array_map(fn($l) => $l['name'], $sportsRepo->getAllLevels());
        $allSports = $sportsRepo->getAllSports();
        $this->renderEditForm($id, $row, $skillLevels, $isReadOnly, [], $allSports);
    }

    public function updateEvent($id): void
    {
        $this->ensureSession();
        $repo = new EventRepository();
        if ($repo->isEventPast((int)$id) && !$this->isAdmin()) {
            http_response_code(403);
            echo json_encode(['error' => 'Cannot edit past events']);
            return;
        }

        $validation = EventFormValidator::validate($_POST);
        if (!empty($validation['errors'])) {
            $row = $repo->getEventById((int)$id);
            $sportsRepo = new SportsRepository();
            $skillLevels = array_map(fn($l) => $l['name'], $sportsRepo->getAllLevels());
            $allSports = $sportsRepo->getAllSports();
            $this->renderEditForm($id, $row, $skillLevels, false, $validation['errors'], $allSports);
            return;
        }
        $updates = [
            'title' => $validation['data']['title'],
            'sport_id' => $validation['data']['sport_id'],
            'start_time' => $validation['data']['start_time'],
            'latitude' => $validation['data']['latitude'],
            'longitude' => $validation['data']['longitude'],
            'level_id' => $validation['data']['level_id'],
            'max_players' => $validation['data']['max_players'],
            'min_needed' => $validation['data']['min_needed'],
            'description' => $validation['data']['description']
        ];
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $tmpName = $_FILES['image']['tmp_name'];
            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $fileName = 'event_' . uniqid() . '.' . $ext;
            $targetPath = __DIR__ . '/../../public/images/events/' . $fileName;
            if (move_uploaded_file($tmpName, $targetPath)) {
                $updates['image_url'] = '/public/images/events/' . $fileName;
            }
        }
        $result = $repo->updateEvent((int)$id, $updates);
        if ($result) {
            header('Location: /event/' . (int)$id);
            exit;
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update event']);
        }
    }

    private function renderEditForm($id, $row, $skillLevels, $isReadOnly, $errors = [], $allSports = []): void
    {
        $minPeople = (int)($row['min_needed'] ?? 6);
        $maxPeople = isset($row['max_players']) && $row['max_players'] > 0 ? (int)$row['max_players'] : null;
        $minimumValue = null;
        $rangeMinValue = null;
        $rangeMaxValue = null;
        $specificValue = null;
        
        if ($minPeople === $maxPeople && $maxPeople !== null) {
            $participantsType = 'specific';
            $specificValue = $minPeople;
        } elseif ($maxPeople === null) {
            $participantsType = 'minimum';
            $minimumValue = $minPeople;
        } else {
            $participantsType = 'range';
            $rangeMinValue = $minPeople;
            $rangeMaxValue = $maxPeople;
        }
        
        $coordsString = '';
        if (isset($row['latitude'], $row['longitude'])) {
            $coordsString = $row['latitude'] . ', ' . $row['longitude'];
        }
        
        $datetimeInput = '';
        if (!empty($row['start_time'])) {
            try {
                $dt = new DateTime($row['start_time']);
                $datetimeInput = $dt->format('Y-m-d\TH:i');
            } catch (Throwable $e) {
                $datetimeInput = '';
            }
        }
        
        $formEvent = [
            'id' => $row['id'] ?? $id,
            'title' => $row['title'] ?? '',
            'datetime' => $datetimeInput,
            'skillLevel' => $row['level_name'] ?? 'Intermediate',
            'sportId' => $row['sport_id'] ?? 0,
            'location' => $coordsString,
            'desc' => $row['description'] ?? '',
            'participants' => [
                'type' => $participantsType,
                'specific' => $specificValue,
                'minimum' => $minimumValue,
                'rangeMin' => $rangeMinValue,
                'rangeMax' => $rangeMaxValue
            ]
        ];

        $sportsRepo = new SportsRepository();

        $renderData = [
            'pageTitle' => 'SportMatch - Edit Event',
            'activeNav' => 'event-edit',
            'skillLevels' => $sportsRepo->getAllLevels(),
            'allSports' => $allSports,
            'event' => $formEvent,
            'eventId' => $id,
            'isReadOnly' => $isReadOnly,
        ];

        if (!empty($errors)) {
            $renderData['errors'] = $errors;
            $renderData['formData'] = $_POST;
        }
        
        parent::render('event-edit', $renderData);
    }

    public function details($id = null, $action = null) {
        $repo = new EventRepository();
        $row = $repo->getEventById((int)$id);
        
        if (!$row) {
            http_response_code(404);
            $this->render('404');
            return;
        }
        
        $userId = $this->getCurrentUserId();
        
        $event = [
            'id' => (int)$row['id'],
            'title' => (string)$row['title'],
            'description' => (string)($row['description'] ?? ''),
            'sportName' => (string)($row['sport_name'] ?? ''),
            'location' => (string)($row['location_text'] ?? ''),
            'coords' => isset($row['latitude'],$row['longitude']) ? ($row['latitude'] . ', ' . $row['longitude']) : null,
            'dateTime' => (new DateTime($row['start_time']))->format('D, M j, g:i A'),
            'skillLevel' => (string)($row['level_name'] ?? 'Intermediate'),
            'levelColor' => (string)($row['level_color'] ?? '#9E9E9E'), // DODANE: Pobieranie koloru z bazy
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
        $this->render('event', [
            'pageTitle' => 'SportMatch - Match Details',
            'activeNav' => 'sports',
            'event' => $event
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
        if (!$this->canDeleteEvent($event, $userId)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Only owner or admin can delete event']);
            exit();
        }
        $result = $repo->deleteEvent((int)$id);
        if ($result) {
            http_response_code(200);
            echo json_encode(['status' => 'success', 'message' => 'Event deleted']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to delete event']);
        }
        exit();
    }

    private function canDeleteEvent($event, $userId): bool {
        return ($this->isAdmin() || ((int)$event['owner_id'] === (int)$userId));
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
