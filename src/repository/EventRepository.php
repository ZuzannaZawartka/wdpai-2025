<?php

require_once __DIR__ . '/../../Database.php';

class EventRepository {
    private PDO $db;

    public function __construct() {
        $this->db = (new Database())->connect();
    }

    public function getMyEvents(int $ownerId): array {
        $sql = "
            SELECT
              e.id,
              e.title,
              e.start_time,
              e.image_url,
              e.max_players,
              l.name AS level_name,
              (SELECT COUNT(*) FROM event_participants p WHERE p.event_id = e.id) AS current_players
            FROM events e
            LEFT JOIN levels l ON l.id = e.level_id
            WHERE e.owner_id = :owner
            ORDER BY e.start_time ASC
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['owner' => $ownerId]);
        $rows = $stmt->fetchAll();
        return array_map(function($r) {
            $current = (int)($r['current_players'] ?? 0);
            $max = (int)($r['max_players'] ?? $current);
            $level = is_string($r['level_name'] ?? null) ? $r['level_name'] : 'Intermediate';
            return [
                'id' => (int)$r['id'],
                'title' => (string)$r['title'],
                'datetime' => $this->formatDateTime($r['start_time'] ?? null),
                'players' => $current . '/' . $max . ' Players',
                'level' => $level,
                'levelColor' => $this->levelColor($level),
                'imageUrl' => (string)($r['image_url'] ?? ''),
            ];
        }, $rows);
    }

    public function deleteEventByOwner(int $eventId, int $ownerId): bool {
        $stmt = $this->db->prepare('DELETE FROM events WHERE id = :id AND owner_id = :owner');
        $stmt->execute(['id' => $eventId, 'owner' => $ownerId]);
        return $stmt->rowCount() > 0;
    }
    
    public function deleteEvent(int $eventId): bool {
        $stmt = $this->db->prepare('DELETE FROM events WHERE id = :id');
        $stmt->execute(['id' => $eventId]);
        return $stmt->rowCount() > 0;
    }

    private function levelColor(string $level): string {
        switch ($level) {
            case 'Beginner': return '#22c55e';
            case 'Advanced': return '#ef4444';
            default: return '#eab308'; // Intermediate or unknown
        }
    }

    private function formatDateTime($dbTs): string {
        if (empty($dbTs)) { return 'TBD'; }
        try {
            $dt = new DateTime($dbTs);
            // Example: Mon, Jan 15, 6:00 PM
            return $dt->format('D, M j, g:i A');
        } catch (Throwable $e) {
            return (string)$dbTs;
        }
    }
}
