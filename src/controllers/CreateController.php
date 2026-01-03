<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repository/MockRepository.php';

class CreateController extends AppController {

    public function index(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->save();
        } else {
            $this->renderForm();
        }
    }

    protected function renderForm(): void
    {
        $allLevels = MockRepository::levels();
        $skillLevels = array_values($allLevels); // Convert to indexed array for form
        parent::render('create', [
            'pageTitle' => 'SportMatch - Create Event',
            'activeNav' => 'create',
            'skillLevels' => $skillLevels,
        ]);
    }

    protected function save(): void
    {
        $this->ensureSession();
        
        // Get form data
        $title = $_POST['title'] ?? '';
        $datetime = $_POST['datetime'] ?? '';
        $location = $_POST['location'] ?? '';
        $skill = $_POST['skill'] ?? 'Intermediate';
        $description = $_POST['desc'] ?? '';
        $participantsType = $_POST['participantsType'] ?? 'range';
        
        // Parse participants based on selected type from form inputs
        $minNeeded = 0;
        $maxPlayers = 0;
        
        if ($participantsType === 'specific') {
            // User selected specific number
            $value = (int)($_POST['playersSpecific'] ?? 0);
            $minNeeded = $value;
            $maxPlayers = $value;
        } elseif ($participantsType === 'minimum') {
            // User selected minimum
            $minNeeded = (int)($_POST['playersMin'] ?? 0);
            $maxPlayers = null;
        } else {
            // User selected range
            $minNeeded = (int)($_POST['playersRangeMin'] ?? 0);
            $maxPlayers = (int)($_POST['playersRangeMax'] ?? 0);
        }
        
        // Get current user as owner
        $ownerId = $this->getCurrentUserId();
        
        // Create new event
        $newEvent = [
            'title' => $title,
            'dateText' => date('D, M j, g:i A', strtotime($datetime)),
            'isoDate' => $datetime,
            'location' => 'Event Location',
            'coords' => $location ?: '0, 0',
            'sportId' => 1,  // Default sport
            'levelId' => $this->skillLevelToId($skill),
            'imageUrl' => 'https://picsum.photos/seed/new-event/800/600',
            'desc' => $description,
            'ownerId' => $ownerId,
            'maxPlayers' => $maxPlayers,
            'minNeeded' => $minNeeded
        ];
        
        // Add to mock repository
        $eventId = MockRepository::addEvent($newEvent);
        
        if ($eventId) {
            header('Location: /event/' . $eventId);
            exit;
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create event']);
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
