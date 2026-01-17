<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repository/EventRepository.php';
require_once __DIR__ . '/../entity/Event.php';

class MyController extends AppController
{

    public function index(): void
    {
        $this->ensureSession();
        $currentUserId = $this->getCurrentUserId();

        if ($this->isPost() && isset($_POST['deleteId'])) {
            $csrf = $_POST['csrf_token'] ?? '';
            if (empty($csrf) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
                $this->respondForbidden('Sesja wygasła, spróbuj ponownie.', null, 'my');
            }

            $deleteId = (int)$_POST['deleteId'];
            if ($deleteId > 0 && $currentUserId) {
                try {
                    $repo = EventRepository::getInstance();
                    $repo->deleteEventByOwner($deleteId, $currentUserId);
                } catch (Throwable $e) {
                    error_log("Failed to delete event: " . $e->getMessage());
                }
            }
            $this->redirect('/my');
        }

        $myEvents = [];
        if ($currentUserId) {
            $repo = EventRepository::getInstance();
            $entities = $repo->getMyEventsEntities($currentUserId);

            $myEvents = array_map(function (Event $e) {
                $current = $e->getCurrentPlayers();
                $max = $e->getMaxPlayers() ?? $current;
                return [
                    'id' => $e->getId(),
                    'title' => $e->getTitle(),
                    'datetime' => $e->getStartTime() ? (new DateTime($e->getStartTime()))->format('D, M j, g:i A') : 'TBD',
                    'players' => $current . '/' . $max . ' Players',
                    'level' => $e->getLevelName(),
                    'levelColor' => $e->getLevelColor(),
                    'imageUrl' => $e->getImageUrl(),
                    'isPast'   => $e->getStartTime() ? (strtotime($e->getStartTime()) < time()) : false,
                ];
            }, $entities);
        }

        $this->render('my', [
            'pageTitle' => 'FindRival - My Events',
            'activeNav' => 'my',
            'myEvents'  => $myEvents,
        ]);
    }
}
