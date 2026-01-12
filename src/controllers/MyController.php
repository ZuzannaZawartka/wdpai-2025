<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repository/EventRepository.php';

class MyController extends AppController {

    public function index(): void
    {
        $this->ensureSession();
        
        // Handle delete action via POST (with CSRF)
        if ($this->isPost() && isset($_POST['deleteId'])) {
            $csrf = $_POST['csrf_token'] ?? '';
            if (empty($csrf) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
                header('HTTP/1.1 403 Forbidden');
                $this->render('my', [
                    'pageTitle' => 'SportMatch - My Events',
                    'activeNav' => 'my',
                    'myEvents' => [],
                    'messages' => 'Sesja wygasła, spróbuj ponownie.'
                ]);
                return;
            }

            $deleteId = (int)$_POST['deleteId'];
            if ($deleteId > 0) {
                $ownerId = $this->getCurrentUserId() ?? 0;
                if ($ownerId > 0) {
                    try {
                        $repo = new EventRepository();
                        $repo->deleteEventByOwner($deleteId, $ownerId);
                    } catch (Throwable $e) {
                        error_log("Failed to delete event: " . $e->getMessage());
                    }
                }
            }
            header('Location: /my');
            exit();
        }

        $currentUserId = $this->getCurrentUserId();
        $myEvents = [];
        
        if ($currentUserId) {
            $repo = new EventRepository();
            $myEvents = $repo->getMyEvents($currentUserId);
        }

        $this->render('my', [
            'pageTitle' => 'SportMatch - My Events',
            'activeNav' => 'my',
            'myEvents' => $myEvents,
        ]);
    }
}
