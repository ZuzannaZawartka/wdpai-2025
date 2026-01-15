<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repository/EventRepository.php';

class MyController extends AppController {

    public function index(): void
    {
        $this->ensureSession();
        $currentUserId = $this->getCurrentUserId();

        if ($this->isPost() && isset($_POST['deleteId'])) {
            $csrf = $_POST['csrf_token'] ?? '';
            if (!empty($csrf) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $csrf)) {
                $deleteId = (int)$_POST['deleteId'];
                if ($deleteId > 0 && $currentUserId) {
                    $repo = new EventRepository();
                    $repo->deleteEventByOwner($deleteId, $currentUserId);
                }
            }
            header('Location: /my');
            exit();
        }

        $myEvents = [];
        if ($currentUserId) {
            $repo = new EventRepository();
            $rawEvents = $repo->getMyEvents($currentUserId);
            
            foreach ($rawEvents as $event) {
                $myEvents[] = $this->mapEventData($event);
            }
        }

        $this->render('my', [
            'pageTitle' => 'SportMatch - My Events',
            'activeNav' => 'my',
            'myEvents'  => $myEvents,
        ]);
    }
}