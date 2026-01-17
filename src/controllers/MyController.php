<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repository/EventRepository.php';
require_once __DIR__ . '/../entity/Event.php';

class MyController extends AppController {

    public function index(): void
    {
        $this->ensureSession();
        $currentUserId = $this->getCurrentUserId();

        if ($this->isPost() && isset($_POST['deleteId'])) {
            $csrf = $_POST['csrf_token'] ?? '';
            if (empty($csrf) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
                 $this->setStatusCode(403);
                 // Fallback or error, but better to just redirect or show error.
                 // Rendering 'my' with error is fine.
                 $this->render('my', [
                    'pageTitle' => 'SportMatch - My Events',
                    'activeNav' => 'my',
                    'myEvents' => [],
                    'messages' => 'Sesja wygasła, spróbuj ponownie.'
                ]);
                return;
            }

            $deleteId = (int)$_POST['deleteId'];
            if ($deleteId > 0 && $currentUserId) {
                try {
                    $repo = new EventRepository();
                    $repo->deleteEventByOwner($deleteId, $currentUserId);
                } catch (Throwable $e) {
                    error_log("Failed to delete event: " . $e->getMessage());
                }
            }
            header('Location: /my');
            exit();
        }

        $myEvents = [];
        if ($currentUserId) {
            $repo = new EventRepository();
            $entities = $repo->getMyEventsEntities($currentUserId);
            
            $myEvents = array_map(function(Event $e) {
                $current = $e->getCurrentPlayers();
                $max = $e->getMaxPlayers() ?? $current;
                $level = $e->getLevelName() ?: 'Intermediate';
                
                return [
                    'id' => $e->getId(),
                    'title' => $e->getTitle(),
                    'datetime' => $e->getStartTime() ? (new DateTime($e->getStartTime()))->format('D, M j, g:i A') : 'TBD',
                    'players' => $current . '/' . $max . ' Players',
                    'level' => $level,
                    'levelColor' => $e->getLevelColor() ?? '#eab308',
                    'imageUrl' => $e->getImageUrl() ?? '',
                ];
            }, $entities);
        }

        $this->render('my', [
            'pageTitle' => 'SportMatch - My Events',
            'activeNav' => 'my',
            'myEvents'  => $myEvents,
        ]);
    }
}