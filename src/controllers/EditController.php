<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repository/EventRepository.php';
require_once __DIR__ . '/../repository/SportsRepository.php';
require_once __DIR__ . '/../validators/EventFormValidator.php';
require_once __DIR__ . '/../dto/UpdateEventDTO.php';
require_once __DIR__ . '/../valueobject/EventMetadata.php';
require_once __DIR__ . '/../valueobject/Location.php';

class EditController extends AppController {

    public function edit($id): void
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
        
        // Validate form using EventFormValidator
        $validation = EventFormValidator::validate($_POST, $currentParticipants);

        if (!empty($validation['errors'])) {
            // Re-render form with errors
            $row = $currentEvent ?? $repo->getEventById((int)$id);
            $sportsRepo = new SportsRepository();
            $skillLevels = array_map(fn($l) => $l['name'], $sportsRepo->getAllLevels());
            $allSports = $sportsRepo->getAllSports();
            $this->renderEditForm($id, $row, $skillLevels, false, $validation['errors'], $allSports);
            return;
        }
        // Use DTO returned by validator for updates
        $validatorDto = $validation['dto'] ?? new UpdateEventDTO($validation['data'] ?? []);
        $updates = $validatorDto->toArray();
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
        
                    // Removed redirect logic as Routing.php handles it
                    // header('Location: /event/' . (int)$id);
                    // exit;
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
        
        // Parse coords
        $coordsString = '';
        if (isset($row['latitude'], $row['longitude'])) {
            $coordsString = $row['latitude'] . ', ' . $row['longitude'];
        }
        
        // Format datetime for input
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
        
        // Add errors and formData only if there are errors
        if (!empty($errors)) {
            $renderData['errors'] = $errors;
            $renderData['formData'] = $_POST;
        }

        parent::render('event-edit', $renderData);
    }
}
