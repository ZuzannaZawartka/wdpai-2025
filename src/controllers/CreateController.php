<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repository/EventRepository.php';
require_once __DIR__ . '/../repository/SportsRepository.php';

class CreateController extends AppController {

    public function index(): void
    {
        // Admins cannot create events
        if ($this->isAdmin()) {
            header('HTTP/1.1 403 Forbidden');
            $this->render('404');
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->save();
        } else {
            $this->renderForm();
        }
    }

    protected function renderForm(): void
    {
        $sportsRepo = new SportsRepository();
        $skillLevels = array_map(fn($l) => $l['name'], $sportsRepo->getAllLevels());
        
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
        $title = trim($_POST['title'] ?? '');
        $datetime = $_POST['datetime'] ?? '';
        $location = $_POST['location'] ?? '';
        $skill = $_POST['skill'] ?? 'Intermediate';
        $description = trim($_POST['desc'] ?? '');
        $participantsType = $_POST['participantsType'] ?? 'range';
        
        // Validation
        $errors = [];
        
        if (empty($title)) {
            $errors[] = 'Event name is required';
        }
        
        if (empty($datetime)) {
            $errors[] = 'Date and time is required';
        }
        
        if (empty($location)) {
            $errors[] = 'Location is required - please choose on map';
        }
        
        if (!empty($errors)) {
            $sportsRepo = new SportsRepository();
            $skillLevels = array_map(fn($l) => $l['name'], $sportsRepo->getAllLevels());
            
            parent::render('create', [
                'pageTitle' => 'SportMatch - Create Event',
                'activeNav' => 'create',
                'skillLevels' => $skillLevels,
                'errors' => $errors,
                'formData' => $_POST
            ]);
            return;
        }
        
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
        
        // Get current user as owner
        $ownerId = $this->getCurrentUserId();
        
        // Create new event
        $newEvent = [
            'owner_id' => $ownerId,
            'title' => $title,
            'description' => $description,
            'sport_id' => 1,  // Default sport
            'location_text' => 'Event Location',
            'latitude' => $latitude,
            'longitude' => $longitude,
            'start_time' => $startTime,
            'level_id' => $this->skillLevelToId($skill),
            'image_url' => 'https://picsum.photos/seed/new-event/800/600',
            'max_players' => $maxPlayers ?? 0,
            'min_needed' => $minNeeded
        ];
        
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
