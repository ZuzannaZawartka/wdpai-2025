<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repository/EventRepository.php';
require_once __DIR__ . '/../entity/Event.php';

class MyController extends AppController {

    public function index(): void
    {
        $this->ensureSession();
        // Handle delete action via POST (with CSRF)
        if ($this->isPost() && isset($_POST['deleteId'])) {
            $csrf = $_POST['csrf_token'] ?? '';
            if (empty($csrf) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
                $this->setStatusCode(403);
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
            $entities = $repo->getMyEventsEntities($currentUserId);
            $myEvents = array_map(function(Event $e) {
                $current = (int)$e->getCurrentPlayers();
                $max = $e->getMaxPlayers() ?? $current;
                $level = $e->getLevelName() ?: 'Intermediate';
                return [
                    'id' => $e->getId(),
                    'title' => $e->getTitle(),
                    'datetime' => (new DateTime($e->getStartTime()))->format('D, M j, g:i A'),
                    'players' => $current . '/' . $max . ' Players',
                    'level' => $level,
                    'levelColor' => $this->isAdmin() ? '#eab308' : ($level === 'Beginner' ? '#22c55e' : ($level === 'Advanced' ? '#ef4444' : '#eab308')),
                    'imageUrl' => $e->getImageUrl() ?? '',
                ];
            }, $entities);
        }

        $this->render('my', [
            'pageTitle' => 'SportMatch - My Events',
            'activeNav' => 'my',
            'myEvents' => $myEvents,
        ]);
    }
}
