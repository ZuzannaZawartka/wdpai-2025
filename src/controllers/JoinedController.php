<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repository/EventRepository.php';
require_once __DIR__ . '/../repository/UserRepository.php';
require_once __DIR__ . '/../entity/Event.php';

class JoinedController extends AppController
{

    public function index(): void
    {
        $this->ensureSession();
        $userId = $this->getCurrentUserId();
        $repo = EventRepository::getInstance();
        $rows = $userId ? $repo->getUserUpcomingEvents($userId) : [];
        // Map rows to Event entities for clearer code
        $events = array_map(fn($r) => new Event($r), $rows);
        $joinedMatches = array_map(function (Event $e) use ($userId) {
            $current = $e->getCurrentPlayers();
            $max = $e->getMaxPlayers() ?? $current;
            return [
                'id' => $e->getId(),
                'title' => $e->getTitle(),
                'datetime' => (new DateTime($e->getStartTime()))->format('D, M j, g:i A'),
                'desc' => $e->getDescription() ?? '',
                'players' => $current . '/' . $max . ' Players',
                'level' => $e->getLevelName(),
                'levelColor' => $e->getLevelColor(),
                'imageUrl' => $e->getImageUrl(),
                'isOwner' => $e->getOwnerId() === (int)$userId,
                'isPast'   => $e->getStartTime() ? (strtotime($e->getStartTime()) < time()) : false,
            ];
        }, $events);
        $this->render('joined', [
            'pageTitle' => 'SportMatch - Joined Events',
            'activeNav' => 'joined',
            'joinedMatches' => $joinedMatches,
        ]);
    }
}
