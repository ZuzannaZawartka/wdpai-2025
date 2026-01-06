<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repository/MockRepository.php';
require_once __DIR__ . '/../repository/EventRepository.php';

class MyController extends AppController {

    public function index(): void
    {
        $this->ensureSession();
        // Handle delete action via POST (with CSRF). Prefer DB deletion, fallback to mock hide.
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
                $deleted = false;
                if ($ownerId > 0) {
                    try {
                        $repo = new EventRepository();
                        $deleted = $repo->deleteEventByOwner($deleteId, $ownerId);
                    } catch (Throwable $e) {
                        $deleted = false;
                    }
                }
                // Fallback to mock hide when DB delete failed/not available
                if (!$deleted) {
                    if (!isset($_SESSION['deleted_event_ids']) || !is_array($_SESSION['deleted_event_ids'])) {
                        $_SESSION['deleted_event_ids'] = [];
                    }
                    if (!in_array($deleteId, $_SESSION['deleted_event_ids'], true)) {
                        $_SESSION['deleted_event_ids'][] = $deleteId;
                    }
                }
            }
            header('Location: /my');
            exit();
        }

        $currentUserId = $this->getCurrentUserId();
        // Always use mock for now (DB not yet synced with mock edits)
        $myEvents = [];
        if ($currentUserId) {
            $myEvents = MockRepository::myEvents($currentUserId);
        } else {
            $myEvents = MockRepository::myEvents(null);
        }

        // Filter out deleted events stored in session (mock delete)
        $deleted = isset($_SESSION['deleted_event_ids']) && is_array($_SESSION['deleted_event_ids']) ? $_SESSION['deleted_event_ids'] : [];
        if (!empty($deleted)) {
            $myEvents = array_values(array_filter($myEvents, function($ev) use ($deleted) {
                return !in_array((int)($ev['id'] ?? 0), $deleted, true);
            }));
        }

        $this->render('my', [
            'pageTitle' => 'SportMatch - My Events',
            'activeNav' => 'my',
            'myEvents' => $myEvents,
        ]);
    }
}
