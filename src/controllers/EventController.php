<?php
require_once 'AppController.php';
require_once __DIR__ . '/../repository/EventRepository.php';
require_once __DIR__ . '/../repository/SportsRepository.php';
require_once __DIR__ . '/../validators/EventFormValidator.php';
require_once __DIR__ . '/../entity/Event.php';
require_once __DIR__ . '/../dto/UpdateEventDTO.php';
require_once __DIR__ . '/../valueobject/EventMetadata.php';
require_once __DIR__ . '/../valueobject/Location.php';

class EventController extends AppController
{
    public function showCreateForm(): void
    {
        // Admins cannot create events
        if ($this->isAdmin()) {
            $this->respondForbidden('Admins cannot create events');
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
            $this->redirect('/event/' . $eventId);
        } else {
            $this->respondInternalError('Failed to create event');
        }
    }

    public function showEditForm($id): void
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

    public function updateEvent($id): void
    {
        $this->ensureSession();
        $repo = new EventRepository();
        if ($repo->isEventPast((int)$id) && !$this->isAdmin()) {
            $this->respondForbidden('Cannot edit past events');
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
                $this->respondBadRequest('Invalid event metadata');
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
                $this->respondBadRequest('Invalid event metadata');
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
            $this->redirect('/event/' . (int)$id);
        } else {
            $this->respondInternalError('Failed to update event');
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

    public function details($id = null, $action = null)
    {
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
            'levelColor' => (string)($eventEntity->getLevelColor() ?? '#9E9E9E'),
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

    public function join($id)
    {
        $userId = $this->getCurrentUserId();
        if (!$userId) {
            $this->respondUnauthorized('Not authenticated');
        }

        $repo = new EventRepository();
        if ($repo->isEventPast((int)$id)) {
            $this->respondBadRequest('Event has already passed');
        }
        if ($repo->isEventFull((int)$id)) {
            $this->respondBadRequest('Event is full');
        }
        $result = $repo->joinEventWithTransaction($userId, (int)$id);

        if ($result) {
            $this->respondOk(['message' => 'Joined event']);
        } else {
            $this->respondBadRequest('Failed to join event');
        }
    }

    public function delete($id)
    {
        $this->ensureSession();
        $userId = $this->getCurrentUserId();
        if (!$userId) {
            $this->respondUnauthorized('Not authenticated');
        }
        $repo = new EventRepository();
        $eventEntity = $repo->getEventEntityById((int)$id);
        if (!$eventEntity) {
            $this->respondNotFound('Event not found');
        }

        if (!$this->canDeleteEvent($eventEntity, $userId)) {
            $this->respondForbidden('Only owner or admin can delete event');
        }

        $result = $repo->deleteEvent((int)$id);
        if ($result) {
            $this->respondOk(['message' => 'Event deleted']);
        } else {
            $this->respondInternalError('Failed to delete event');
        }
    }

    private function canDeleteEvent(\Event $event, int $userId): bool
    {
        return ($this->isAdmin() || ((int)$event->getOwnerId() === (int)$userId));
    }

    public function leave($id)
    {
        $this->ensureSession();
        $userId = $this->getCurrentUserId();
        if (!$userId) {
            $this->respondUnauthorized('Not authenticated');
        }
        $repo = new EventRepository();
        $eventEntity = $repo->getEventEntityById((int)$id);
        if (!$eventEntity) {
            $this->respondNotFound('Event not found');
        }

        if ((int)$eventEntity->getOwnerId() === (int)$userId) {
            $this->respondBadRequest('Owner cannot leave their own event');
        }
        $result = $repo->cancelParticipationWithTransaction($userId, (int)$id);
        if ($result) {
            $this->respondOk(['message' => 'Left event']);
        } else {
            $this->respondBadRequest('Failed to leave event');
        }
    }
}
