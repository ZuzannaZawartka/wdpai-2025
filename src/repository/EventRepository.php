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

    public function getAllForListing(bool $includePast = true): array {
        $where = $includePast ? '' : 'WHERE e.start_time >= NOW()';
        $sql = "
            SELECT e.id, e.title, e.description, e.sport_id, s.name AS sport_name,
                   e.location_text, e.latitude, e.longitude,
                   e.start_time, e.level_id, l.name AS level_name,
                   e.image_url, e.max_players, e.min_needed,
                   e.owner_id,
                   (SELECT COUNT(*) FROM event_participants p WHERE p.event_id = e.id) AS current_players
            FROM events e
            LEFT JOIN sports s ON s.id = e.sport_id
            LEFT JOIN levels l ON l.id = e.level_id
            $where
            ORDER BY e.start_time DESC
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getEventById(int $id): ?array {
        $sql = "
            SELECT e.id, e.title, e.description, e.sport_id, s.name AS sport_name,
                   e.location_text, e.latitude, e.longitude,
                   e.start_time, e.level_id, l.name AS level_name,
                   e.image_url, e.max_players, e.min_needed,
                   e.owner_id, u.email AS owner_email, u.firstname, u.lastname, u.avatar_url,
                   (SELECT COUNT(*) FROM event_participants p WHERE p.event_id = e.id) AS current_players
            FROM events e
            LEFT JOIN sports s ON s.id = e.sport_id
            LEFT JOIN levels l ON l.id = e.level_id
            LEFT JOIN users u ON u.id = e.owner_id
            WHERE e.id = :id
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getUpcomingWithCoords(): array {
        $sql = "
            SELECT e.id, e.title, e.location_text, e.latitude, e.longitude,
                   e.start_time, e.sport_id, s.name AS sport_name,
                   e.image_url, e.max_players,
                   (SELECT COUNT(*) FROM event_participants p WHERE p.event_id = e.id) AS current_players
            FROM events e
            LEFT JOIN sports s ON s.id = e.sport_id
            WHERE e.start_time >= NOW() AND e.latitude IS NOT NULL AND e.longitude IS NOT NULL
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getUserUpcomingEvents(int $userId): array {
        $sql = "
            SELECT e.id, e.title, e.description, e.start_time, e.image_url, e.max_players, e.location_text, e.owner_id,
                   l.name AS level_name,
                   (SELECT COUNT(*) FROM event_participants p WHERE p.event_id = e.id) AS current_players
            FROM event_participants ep
            JOIN events e ON e.id = ep.event_id
            LEFT JOIN levels l ON l.id = e.level_id
            WHERE ep.user_id = :uid AND e.start_time >= NOW()
            ORDER BY e.start_time ASC
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function isEventPast(int $eventId): bool {
        $stmt = $this->db->prepare('SELECT start_time < NOW() AS past FROM events WHERE id = ?');
        $stmt->execute([$eventId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return !empty($row['past']);
    }

    public function isEventFull(int $eventId): bool {
        $stmt = $this->db->prepare('SELECT max_players FROM events WHERE id = ?');
        $stmt->execute([$eventId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $max = (int)($row['max_players'] ?? 0);
        $stmt2 = $this->db->prepare('SELECT COUNT(*) AS c FROM event_participants WHERE event_id = ?');
        $stmt2->execute([$eventId]);
        $c = (int)($stmt2->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
        return $max > 0 && $c >= $max;
    }

    public function joinEvent(int $userId, int $eventId): bool {
        try {
            $ins = $this->db->prepare('INSERT INTO event_participants(event_id, user_id) VALUES(?, ?)');
            return $ins->execute([$eventId, $userId]);
        } catch (Throwable $e) {
            return false;
        }
    }

    public function isUserParticipant(int $userId, int $eventId): bool {
        $stmt = $this->db->prepare('SELECT 1 FROM event_participants WHERE event_id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$eventId, $userId]);
        return $stmt->fetch() !== false;
    }

    public function cancelParticipation(int $userId, int $eventId): bool {
        $stmt = $this->db->prepare('DELETE FROM event_participants WHERE event_id = ? AND user_id = ?');
        $stmt->execute([$eventId, $userId]);
        return $stmt->rowCount() > 0;
    }

    public function participantsCount(int $eventId): int {
        $stmt = $this->db->prepare('SELECT COUNT(*) AS c FROM event_participants WHERE event_id = ?');
        $stmt->execute([$eventId]);
        return (int)($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
    }

    public function createEvent(array $data): ?int {
        $sql = "
            INSERT INTO events (owner_id, title, description, sport_id, location_text, latitude, longitude,
                                start_time, level_id, image_url, max_players, min_needed)
            VALUES (:owner_id, :title, :description, :sport_id, :location_text, :latitude, :longitude,
                    :start_time, :level_id, :image_url, :max_players, :min_needed)
        ";
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            ':owner_id' => $data['owner_id'] ?? null,
            ':title' => $data['title'] ?? '',
            ':description' => $data['description'] ?? '',
            ':sport_id' => $data['sport_id'] ?? 1,
            ':location_text' => $data['location_text'] ?? '',
            ':latitude' => $data['latitude'] ?? null,
            ':longitude' => $data['longitude'] ?? null,
            ':start_time' => $data['start_time'] ?? null,
            ':level_id' => $data['level_id'] ?? null,
            ':image_url' => $data['image_url'] ?? null,
            ':max_players' => $data['max_players'] ?? 0,
            ':min_needed' => $data['min_needed'] ?? 0,
        ]);
        return $result ? (int)$this->db->lastInsertId() : null;
    }

    public function updateEvent(int $eventId, array $data): bool {
        $fields = [];
        $params = [':id' => $eventId];
        
        if (isset($data['title'])) {
            $fields[] = 'title = :title';
            $params[':title'] = $data['title'];
        }
        if (isset($data['description'])) {
            $fields[] = 'description = :description';
            $params[':description'] = $data['description'];
        }
        if (isset($data['sport_id'])) {
            $fields[] = 'sport_id = :sport_id';
            $params[':sport_id'] = $data['sport_id'];
        }
        if (isset($data['location_text'])) {
            $fields[] = 'location_text = :location_text';
            $params[':location_text'] = $data['location_text'];
        }
        if (array_key_exists('latitude', $data)) {
            $fields[] = 'latitude = :latitude';
            $params[':latitude'] = $data['latitude'];
        }
        if (array_key_exists('longitude', $data)) {
            $fields[] = 'longitude = :longitude';
            $params[':longitude'] = $data['longitude'];
        }
        if (isset($data['start_time'])) {
            $fields[] = 'start_time = :start_time';
            $params[':start_time'] = $data['start_time'];
        }
        if (isset($data['level_id'])) {
            $fields[] = 'level_id = :level_id';
            $params[':level_id'] = $data['level_id'];
        }
        if (isset($data['image_url'])) {
            $fields[] = 'image_url = :image_url';
            $params[':image_url'] = $data['image_url'];
        }
        if (isset($data['max_players'])) {
            $fields[] = 'max_players = :max_players';
            $params[':max_players'] = $data['max_players'];
        }
        if (isset($data['min_needed'])) {
            $fields[] = 'min_needed = :min_needed';
            $params[':min_needed'] = $data['min_needed'];
        }
        
        $fields[] = 'updated_at = NOW()';
        
        if (empty($fields)) {
            return false;
        }
        
        $sql = "UPDATE events SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
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
