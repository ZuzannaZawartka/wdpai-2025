<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repository/EventRepository.php';
require_once __DIR__ . '/../repository/SportsRepository.php';
require_once __DIR__ . '/../validators/EventFormValidator.php';
require_once __DIR__ . '/../dto/CreateEventDTO.php';
require_once __DIR__ . '/../dto/UpdateEventDTO.php';
require_once __DIR__ . '/../valueobject/EventMetadata.php';
require_once __DIR__ . '/../valueobject/Location.php';

class CreateController extends AppController {

    public function index(): void
    {
        if ($this->isAdmin()) {
            $this->respondForbidden();
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
        $allSports = $sportsRepo->getAllSports();
        
        parent::render('create', [
            'pageTitle' => 'SportMatch - Create Event',
            'activeNav' => 'create',
            'skillLevels' => $skillLevels,
            'allSports' => $allSports,
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
        // Build DTO from validator (UpdateEventDTO is returned by validator)
        $validatorDto = $validation['dto'] ?? new UpdateEventDTO($validation['data'] ?? []);
        $createArray = array_merge($validatorDto->toArray(), [
            'owner_id' => $ownerId,
            'image_url' => $imageUrl
        ]);
        // Use explicit CreateEventDTO for clarity
        $createDto = new CreateEventDTO($createArray);
        $repo = new EventRepository();
        $eventId = $repo->createEvent($createDto->toArray());
        if ($eventId) {
            header('Location: /event/' . $eventId);
            exit;
        } else {
            $this->respondInternalError('Failed to create event', true);
        }
    }
}
