<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repository/EventRepository.php';
require_once __DIR__ . '/../repository/SportsRepository.php';
require_once __DIR__ . '/../validators/EventFormValidator.php';

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
            http_response_code(403);
            echo json_encode(['error' => 'Cannot edit past events']);
            return;
        }
        
        // Validate form using EventFormValidator
        $validation = EventFormValidator::validate($_POST);
        
        if (!empty($validation['errors'])) {
            // Re-render form with errors
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
        
        $participantsType = 'range';
        $specificValue = null;
        $minimumValue = null;
        $rangeMinValue = null;
        $rangeMaxValue = null;
        
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
            'activeNav' => 'edit',
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

        parent::render('edit', $renderData);
    }
}
