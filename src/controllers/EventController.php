<?php
require_once 'AppController.php';
require_once __DIR__ . '/../repository/EventRepository.php';
require_once __DIR__ . '/../repository/SportsRepository.php';
require_once __DIR__ . '/../validators/EventFormValidator.php';

class EventController extends AppController {
    public function edit($id): void
    {
        $this->ensureSession();
        $repo = new EventRepository();
        $row = $repo->getEventById((int)$id);
        if (!$row) {
            $this->respondNotFound();
        }
        $isReadOnly = !$this->isAdmin() && $repo->isEventPast((int)$id);
        $sportsRepo = new SportsRepository();
        $skillLevels = array_map(fn($l) => $l['name'], $sportsRepo->getAllLevels());
        $allSports = $sportsRepo->getAllSports();
        $this->renderEditForm($id, $row, $skillLevels, $isReadOnly, [], $allSports);
    }

    public function save($id): void
    {
        $this->ensureSession();
        $repo = new EventRepository();
        if ($repo->isEventPast((int)$id) && !$this->isAdmin()) {
            $this->respondForbidden('Cannot edit past events', true);
        }

        // Get current event to check participants count
        $currentEvent = $repo->getEventById((int)$id);
        $currentParticipants = $currentEvent ? (int)($currentEvent['current_players'] ?? 0) : null;
        
        $validation = EventFormValidator::validate($_POST, $currentParticipants);
        if (!empty($validation['errors'])) {
            $row = $currentEvent ?? $repo->getEventById((int)$id);
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
            $this->respondInternalError('Failed to update event', true);
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
        $renderData = [
            'pageTitle' => 'SportMatch - Edit Event',
            'activeNav' => 'event-edit',
            'skillLevels' => $skillLevels,
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
            $this->respondNotFound();
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
        $userId = $this->getCurrentUserId();
        if (!$userId) {
            $this->respondUnauthorized('Not authenticated', true);
        }

        $repo = new EventRepository();
        if ($repo->isEventPast((int)$id)) {
            $this->respondBadRequest('Event has already passed', true);
        }
        if ($repo->isEventFull((int)$id)) {
            $this->respondBadRequest('Event is full', true);
        }
        $result = $repo->joinEventWithTransaction($userId, (int)$id);
        
        if ($result) {
            $this->respondOk(['message' => 'Joined event']);
        } else {
            $this->respondBadRequest('Failed to join event', true);
        }
    }

    public function delete($id) {
        $this->ensureSession();
        $userId = $this->getCurrentUserId();
        if (!$userId) {
            $this->respondUnauthorized('Not authenticated', true);
        }
        $repo = new EventRepository();
        $event = $repo->getEventById((int)$id);
        if (!$event) {
            $this->respondNotFound('Event not found', true);
        }
        $isOwner = isset($event['owner_id']) && ((int)$event['owner_id'] === (int)$userId);
        $isAdmin = $this->isAdmin();
        $repo = new EventRepository();
        if ($isOwner) {
            $result = $repo->deleteEventByOwner((int)$id, (int)$userId);
        } elseif ($isAdmin) {
            $result = $repo->deleteEvent((int)$id);
        } else {
            $this->respondForbidden('Only owner or admin can delete event', true);
        }
        if ($result) {
            $this->respondOk(['message' => 'Event deleted']);
        } else {
            $this->respondInternalError('Failed to delete event', true);
        }
    }

    public function leave($id) {
        $this->ensureSession();
        $userId = $this->getCurrentUserId();
        if (!$userId) {
            $this->respondUnauthorized('Not authenticated', true);
        }
        $repo = new EventRepository();
        $event = $repo->getEventById((int)$id);
        if (!$event) {
            $this->respondNotFound('Event not found', true);
        }
        if ((int)$event['owner_id'] === (int)$userId) {
            $this->respondBadRequest('Owner cannot leave their own event', true);
        }
        $result = $repo->cancelParticipationWithTransaction($userId, (int)$id);
        if ($result) {
            $this->respondOk(['message' => 'Left event']);
        } else {
            $this->respondBadRequest('Failed to leave event', true);
        }
    }

    public function deleteEventByAdmin() {
        $this->requireRole('admin');
        if (!$this->isPost() && !$this->isDelete()) {
            $this->respondMethodNotAllowed('Method not allowed', true);
        }
        $eventId = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        if (!$eventId) {
            $this->respondBadRequest('Event ID required', true);
        }
        $repo = new EventRepository();
        try {
            $deleted = $repo->deleteEvent($eventId);
            if ($deleted) {
                $this->respondOk(['success' => true]);
            } else {
                $this->respondNotFound('Event not found', true);
            }
        } catch (Throwable $e) {
            error_log("Admin event delete error: " . $e->getMessage());
            $this->respondInternalError('Failed to delete event', true);
        }
    }
}
