<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repository/EventRepository.php';
require_once __DIR__ . '/../repository/SportsRepository.php';
require_once __DIR__ . '/../validators/EventFormValidator.php';

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
        
        // Validate form using EventFormValidator
        $validation = EventFormValidator::validate($_POST);
        
        if (!empty($validation['errors'])) {
            $sportsRepo = new SportsRepository();
            $skillLevels = array_map(fn($l) => $l['name'], $sportsRepo->getAllLevels());
            
            parent::render('create', [
                'pageTitle' => 'SportMatch - Create Event',
                'activeNav' => 'create',
                'skillLevels' => $skillLevels,
                'errors' => $validation['errors'],
                'formData' => $_POST
            ]);
            return;
        }
        
        // Get current user as owner
        $ownerId = $this->getCurrentUserId();
        
        // Create event with validated data
        $newEvent = array_merge($validation['data'], [
            'owner_id' => $ownerId,
            'sport_id' => 1,  // Default sport
            'image_url' => 'https://picsum.photos/seed/new-event/800/600'
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
}
