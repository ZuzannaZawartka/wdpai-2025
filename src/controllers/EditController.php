<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repository/EventRepository.php';
require_once __DIR__ . '/../repository/SportsRepository.php';

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
        
        if (!$this->isAdmin() && $repo->isEventPast((int)$id)) {
            $this->render('404');
            return;
        }
        
        $sportsRepo = new SportsRepository();
        $skillLevels = array_map(fn($l) => $l['name'], $sportsRepo->getAllLevels());
        
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

        $this->render('edit', [
            'pageTitle' => 'SportMatch - Edit Event',
            'activeNav' => 'edit',
            'skillLevels' => $skillLevels,
            'event' => $formEvent,
            'eventId' => $id,
        ]);
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
        
        $title = $_POST['title'] ?? '';
        $datetime = $_POST['datetime'] ?? '';
        $location = $_POST['location'] ?? '';
        $skill = $_POST['skill'] ?? 'Intermediate';
        $desc = $_POST['desc'] ?? '';
        $participantsType = $_POST['participantsType'] ?? 'range';
        
        $minNeeded = 0;
        $maxPlayers = null;
        
        if ($participantsType === 'specific') {
            $raw = $_POST['playersSpecific'] ?? '';
            $value = ($raw === '' ? 0 : (int)$raw);
            $minNeeded = $value;
            $maxPlayers = $value;
        } elseif ($participantsType === 'minimum') {
            $raw = $_POST['playersMin'] ?? '';
            $minNeeded = ($raw === '' ? 0 : (int)$raw);
            $maxPlayers = null;
        } else {
            $rawMin = $_POST['playersRangeMin'] ?? '';
            $rawMax = $_POST['playersRangeMax'] ?? '';
            $minNeeded = ($rawMin === '' ? 0 : (int)$rawMin);
            $maxPlayers = ($rawMax === '' ? 0 : (int)$rawMax);
        }
        
        // Parse location coords
        $latitude = null;
        $longitude = null;
        if (!empty($location) && str_contains($location, ',')) {
            $parts = array_map('trim', explode(',', $location));
            if (count($parts) === 2 && is_numeric($parts[0]) && is_numeric($parts[1])) {
                $latitude = (float)$parts[0];
                $longitude = (float)$parts[1];
            }
        }
        
        // Format datetime for DB
        $startTime = null;
        if (!empty($datetime)) {
            try {
                $dt = new DateTime($datetime);
                $startTime = $dt->format('Y-m-d H:i:s');
            } catch (Throwable $e) {
                $startTime = null;
            }
        }
        
        $updates = [
            'title' => $title,
            'start_time' => $startTime,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'level_id' => $this->skillLevelToId($skill),
            'max_players' => $maxPlayers ?? 0,
            'min_needed' => $minNeeded,
            'description' => $desc
        ];
        
        $result = $repo->updateEvent((int)$id, $updates);
        
        if ($result) {
            header('Location: /event/' . (int)$id);
            exit;
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update event']);
        }
    }
    
    private function skillLevelToId(string $skill): int
    {
        $map = [
            'Beginner' => 1,
            'Intermediate' => 2,
            'Advanced' => 3,
        ];
        return $map[$skill] ?? 2;
    }
}
