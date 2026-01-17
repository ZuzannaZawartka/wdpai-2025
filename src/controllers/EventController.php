<?php
require_once 'AppController.php';
require_once __DIR__ . '/../repository/EventRepository.php';
require_once __DIR__ . '/../repository/SportsRepository.php';
require_once __DIR__ . '/../validators/EventFormValidator.php';
require_once __DIR__ . '/../entity/Event.php';
require_once __DIR__ . '/../dto/UpdateEventDTO.php';
require_once __DIR__ . '/../valueobject/EventMetadata.php';
require_once __DIR__ . '/../valueobject/Location.php';

class EventController extends AppController {
    public function edit($id): void
    {
        $this->ensureSession();
        $repo = new EventRepository();
        $event = $repo->getEventEntityById((int)$id);
        if (!$event) {
            $this->respondNotFound();
        }
        $isReadOnly = !$this->isAdmin() && $repo->isEventPast((int)$id);
        $sportsRepo = new SportsRepository();
        $skillLevels = array_map(fn($l) => $l['name'], $sportsRepo->getAllLevels());
        $allSports = $sportsRepo->getAllSports();
        $this->renderEditForm($id, $event, $skillLevels, $isReadOnly, [], $allSports);
    }

    public function save($id): void
    {
        $this->ensureSession();
        $repo = new EventRepository();
        if ($repo->isEventPast((int)$id) && !$this->isAdmin()) {
            $this->respondForbidden('Cannot edit past events', true);
        }

        // Get current event to check participants count
        $currentEntity = $repo->getEventEntityById((int)$id);
        $currentParticipants = $currentEntity ? (int)$currentEntity->getCurrentPlayers() : null;
        
        $validation = EventFormValidator::validate($_POST, $currentParticipants);
        if (!empty($validation['errors'])) {
            $eventObj = $currentEntity ?? $repo->getEventEntityById((int)$id);
            $sportsRepo = new SportsRepository();
            $skillLevels = array_map(fn($l) => $l['name'], $sportsRepo->getAllLevels());
            $allSports = $sportsRepo->getAllSports();
            $this->renderEditForm($id, $eventObj, $skillLevels, false, $validation['errors'], $allSports);
            return;
        }
        
        // Prefer DTO provided by validator when available
        $dtoFromValidator = $validation['dto'] ?? null;
        if ($dtoFromValidator instanceof \UpdateEventDTO) {
            try {
                $metadata = new EventMetadata($dtoFromValidator->title ?? ($dtoFromValidator->title ?? ''), $dtoFromValidator->description ?? null);
            } catch (Throwable $e) {
                $this->respondBadRequest('Invalid event metadata', true);
            }
            // Normalize via metadata
            $updates = $dtoFromValidator->toArray();
            if (isset($updates['title'])) $updates['title'] = $metadata->title();
            if (isset($updates['description'])) $updates['description'] = $metadata->description();
        } else {
            $dtoData = $validation['data'];
            try {
                $metadata = new EventMetadata($dtoData['title'], $dtoData['description']);
            } catch (Throwable $e) {
                $this->respondBadRequest('Invalid event metadata', true);
            }
            $updateDto = new UpdateEventDTO([
                'title' => $metadata->title(),
                'description' => $metadata->description(),
                'sport_id' => $dtoData['sport_id'],
                'start_time' => $dtoData['start_time'],
                'latitude' => $dtoData['latitude'],
                'longitude' => $dtoData['longitude'],
                'level_id' => $dtoData['level_id'],
                'max_players' => $dtoData['max_players'],
                'min_needed' => $dtoData['min_needed'],
            ]);
            $updates = $updateDto->toArray();
        }
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

    private function renderEditForm($id, \Event $event, $skillLevels, $isReadOnly, $errors = [], $allSports = []): void
    {
        $minPeople = (int)($event->getMinNeeded() ?? 6);
        $maxPlayers = $event->getMaxPlayers();
        $maxPeople = isset($maxPlayers) && $maxPlayers > 0 ? (int)$maxPlayers : null;
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
        if ($event->getLatitude() !== null && $event->getLongitude() !== null) {
            $coordsString = $event->getLatitude() . ', ' . $event->getLongitude();
        }
        $datetimeInput = '';
        if (!empty($event->getStartTime())) {
            try {
                $dt = new DateTime($event->getStartTime());
                $datetimeInput = $dt->format('Y-m-d\TH:i');
            } catch (Throwable $e) {
                $datetimeInput = '';
            }
        }
        $formEvent = [
            'id' => $event->getId() ?? $id,
            'title' => $event->getTitle() ?? '',
            'datetime' => $datetimeInput,
            'skillLevel' => $event->getLevelName() ?? 'Intermediate',
            'sportId' => $event->getSportId() ?? 0,
            'location' => $coordsString,
            'desc' => $event->getDescription() ?? '',
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
        $eventEntity = $repo->getEventEntityById((int)$id);
        if (!$eventEntity) {
            $this->respondNotFound();
        }
        $userId = $this->getCurrentUserId();
        $event = [
            'id' => $eventEntity->getId(),
            'title' => $eventEntity->getTitle(),
            'description' => (string)($eventEntity->getDescription() ?? ''),
            'sportName' => (string)($eventEntity->getSportName() ?? ''),
            'location' => (string)($eventEntity->getLocationText() ?? ''),
            'coords' => ($eventEntity->getLatitude() !== null && $eventEntity->getLongitude() !== null) ? ($eventEntity->getLatitude() . ', ' . $eventEntity->getLongitude()) : null,
            'dateTime' => $eventEntity->getStartTime() ? (new DateTime($eventEntity->getStartTime()))->format('D, M j, g:i A') : '',
            'skillLevel' => (string)($eventEntity->getLevelName() ?? 'Intermediate'),
            'organizer' => [
                'name' => trim(($eventEntity->getOwnerFirstName() ?? '') . ' ' . ($eventEntity->getOwnerLastName() ?? '')) ?: 'Organizer',
                'email' => (string)($eventEntity->getOwnerEmail() ?? ''),
                'avatar' => (string)($eventEntity->getOwnerAvatarUrl() ?? '')
            ],
            'participants' => [
                'current' => $eventEntity->getCurrentPlayers(),
                'max' => (int)($eventEntity->getMaxPlayers() ?? 0),
                'minNeeded' => (int)($eventEntity->getMinNeeded() ?? 0)
            ]
        ];
        if ($userId) {
            $isOwner = (int)$eventEntity->getOwnerId() === (int)$userId;
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
        $event = $repo->getEventEntityById((int)$id);
        if (!$event) {
            $this->respondNotFound('Event not found', true);
        }
        $isOwner = ($event->getOwnerId() !== null) && ((int)$event->getOwnerId() === (int)$userId);
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
