<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repository/EventRepository.php';
require_once __DIR__ . '/../repository/UserRepository.php';

class JoinedController extends AppController {

    public function index(): void
    {
        $this->ensureSession();
        $userId = $this->getCurrentUserId();
        
        // If admin, show accounts instead
        if ($this->isAdmin()) {
            $this->adminAccounts();
            return;
        }
        
        $repo = new EventRepository();
        $rows = $userId ? $repo->getUserUpcomingEvents($userId) : [];
        
        // Map to view format
        $joinedMatches = array_map(function($r) use ($userId) {
            $current = (int)($r['current_players'] ?? 0);
            $max = (int)($r['max_players'] ?? $current);
            $level = is_string($r['level_name'] ?? null) ? $r['level_name'] : 'Intermediate';
            return [
                'id' => (int)$r['id'],
                'title' => (string)$r['title'],
                'datetime' => (new DateTime($r['start_time']))->format('D, M j, g:i A'),
                'desc' => (string)($r['description'] ?? ''),
                'players' => $current . '/' . $max . ' Players',
                'level' => $level,
                'levelColor' => $level === 'Beginner' ? '#22c55e' : ($level === 'Advanced' ? '#ef4444' : '#eab308'),
                'imageUrl' => (string)($r['image_url'] ?? ''),
                'isOwner' => (int)($r['owner_id'] ?? 0) === (int)$userId,
            ];
        }, $rows);

        $this->render('joined', [
            'pageTitle' => 'SportMatch - Joined Events',
            'activeNav' => 'joined',
            'joinedMatches' => $joinedMatches,
        ]);
    }
    
    private function adminAccounts(): void {
        $userRepo = new UserRepository();
        $users = $userRepo->getUsers();
        
        $this->render('accounts', [
            'pageTitle' => 'SportMatch - Accounts',
            'activeNav' => 'joined',
            'accounts' => $users,
        ]);
    }
}
