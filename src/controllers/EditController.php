<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repository/MockRepository.php';

class EditController extends AppController {

    public function edit($id): void
    {
        $this->ensureSession();
        
        $event = MockRepository::getEventById((int)$id);
        
        if (!$this->isAdmin() && MockRepository::isEventPast((int)$id)) {
            $this->render('404');
            return;
        }
        
        $skillLevels = array_values(MockRepository::levels());
        
        $minPeople = $event['participants']['minNeeded'] ?? 6;
        $maxPeople = array_key_exists('max', $event['participants']) ? $event['participants']['max'] : null;
        
        $participantsType = 'range';
        $specificValue = null;
        $minimumValue = null;
        $rangeMinValue = null;
        $rangeMaxValue = null;
        
        if ($minPeople === $maxPeople) {
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
        
        $formEvent = [
            'id' => $event['id'] ?? $id,
            'title' => $event['title'] ?? '',
            'datetime' => $event['dateTime'] ? date('Y-m-d\TH:i', strtotime($event['dateTime'])) : '',
            'skillLevel' => $event['skillLevel'] ?? 'Intermediate',
            'location' => $event['coords'] ?? '',
            'desc' => $event['desc'] ?? '',
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
        
        if (MockRepository::isEventPast((int)$id) && !$this->isAdmin()) {
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
        
        $updates = [
            'title' => $title,
            'dateText' => date('D, M j, g:i A', strtotime($datetime)),
            'isoDate' => $datetime,
            'coords' => $location,
            'levelId' => $this->skillLevelToId($skill),
            'maxPlayers' => $maxPlayers,
            'minNeeded' => $minNeeded,
            'desc' => $desc
        ];
        
        $result = MockRepository::updateEvent((int)$id, $updates);
        
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
