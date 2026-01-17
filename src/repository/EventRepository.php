<?php

require_once __DIR__ . '/../../Database.php';
require_once __DIR__ . '/../entity/Event.php';

require_once __DIR__ . '/Repository.php';

class EventRepository extends Repository
{
    public function __construct()
    {
        parent::__construct();
    }

    private function setAuditUser(int $userId): void
    {
        try {
            $stmt = $this->database->connect()->prepare("SELECT set_config('app.user_id', :uid, true)");
            $stmt->execute([':uid' => (string)$userId]);
        } catch (Throwable $e) {
            // Audit context is optional; ignore failures.
        }
    }

    public function getMyEvents(int $ownerId): array
    {
        $sql = "
            SELECT
                e.id, e.title, e.start_time, e.image_url, e.max_players, e.min_needed, e.description,
                e.latitude, e.longitude,
                l.name AS level_name,
                l.hex_color AS level_color, -- POPRAWIONE: Dodano kolor
                (SELECT COUNT(*) FROM event_participants p WHERE p.event_id = e.id) AS current_players
            FROM events e
            LEFT JOIN levels l ON l.id = e.level_id
            WHERE e.owner_id = :owner AND e.start_time >= NOW()
            ORDER BY e.start_time ASC
        ";
        $stmt = $this->database->connect()->prepare($sql);
        $stmt->execute(['owner' => $ownerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // New: return Event entity list for owner's events (future-proof)
    public function getMyEventsEntities(int $ownerId): array
    {
        require_once __DIR__ . '/../entity/Event.php';
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
            WHERE e.owner_id = :owner
            ORDER BY e.start_time DESC
        ";
        $stmt = $this->database->connect()->prepare($sql);
        $stmt->execute(['owner' => $ownerId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_map(fn($r) => new \Event($r), $rows);
    }

    public function deleteEventByOwner(int $eventId, int $ownerId): bool
    {
        $stmt = $this->database->connect()->prepare('DELETE FROM events WHERE id = :id AND owner_id = :owner');
        $stmt->execute(['id' => $eventId, 'owner' => $ownerId]);
        return $stmt->rowCount() > 0;
    }

    public function deleteEvent(int $eventId): bool
    {
        $stmt = $this->database->connect()->prepare('DELETE FROM events WHERE id = :id');
        $stmt->execute(['id' => $eventId]);
        return $stmt->rowCount() > 0;
    }

    public function getAllForListing(bool $includePast = true): array
    {
        $where = $includePast ? '' : 'WHERE e.start_time >= NOW()';
        $sql = "
            SELECT 
                e.*, 
                s.name AS sport_name, 
                l.name AS level_name, 
                l.hex_color AS level_color, -- POPRAWIONE: Dodano kolor
                (SELECT COUNT(*) FROM event_participants p WHERE p.event_id = e.id) AS current_players
            FROM events e
            LEFT JOIN sports s ON s.id = e.sport_id
            LEFT JOIN levels l ON l.id = e.level_id
            $where
            ORDER BY e.start_time DESC
        ";
        $stmt = $this->database->connect()->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        // Provide both associative-array API and entity API via a separate method; this keeps backward compatibility.
        return $rows;
    }

    // New: return list of Event entities for listing
    public function getAllForListingEntities(bool $includePast = true): array
    {
        $rows = $this->getAllForListing($includePast);
        return array_map(fn($r) => new \Event($r), $rows);
    }

    public function getEventById(int $id): ?array
    {
        $sql = "
            SELECT 
                e.*, s.name AS sport_name,
                l.name AS level_name, 
                l.hex_color AS level_color, -- DODANE
                u.email AS owner_email, u.firstname, u.lastname, u.avatar_url,
                (SELECT COUNT(*) FROM event_participants p WHERE p.event_id = e.id) AS current_players
            FROM events e
            LEFT JOIN sports s ON s.id = e.sport_id
            LEFT JOIN levels l ON l.id = e.level_id
            LEFT JOIN users u ON u.id = e.owner_id
            WHERE e.id = :id
        ";
        $stmt = $this->database->connect()->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // New: return Event entity instance or null
    public function getEventEntityById(int $id): ?\Event
    {
        $row = $this->getEventById($id);
        if (!$row) {
            return null;
        }
        return new \Event($row);
    }

    public function getUpcomingWithCoords(): array
    {
        $sql = "
            SELECT e.id, e.title, e.location_text, e.latitude, e.longitude,
                   e.start_time, e.sport_id, s.name AS sport_name,
                   e.image_url, e.max_players,
                   (SELECT COUNT(*) FROM event_participants p WHERE p.event_id = e.id) AS current_players
            FROM events e
            LEFT JOIN sports s ON s.id = e.sport_id
            WHERE e.start_time >= NOW() AND e.latitude IS NOT NULL AND e.longitude IS NOT NULL
        ";
        $stmt = $this->database->connect()->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getUserUpcomingEvents(int $userId, bool $upcomingOnly = false): array
    {
        $whereTime = $upcomingOnly ? " AND e.start_time >= NOW()" : "";
        $sql = "
            SELECT e.id, e.title, e.description, e.start_time, e.image_url, e.max_players, e.location_text, e.owner_id,
                   l.name AS level_name,
                   (SELECT COUNT(*) FROM event_participants p WHERE p.event_id = e.id) AS current_players
            FROM event_participants ep
            JOIN events e ON e.id = ep.event_id
            LEFT JOIN levels l ON l.id = e.level_id
            WHERE ep.user_id = :uid $whereTime
            ORDER BY e.start_time DESC
        ";
        $stmt = $this->database->connect()->prepare($sql);
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function isEventPast(int $eventId): bool
    {
        $stmt = $this->database->connect()->prepare('SELECT start_time < NOW() AS past FROM events WHERE id = ?');
        $stmt->execute([$eventId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return !empty($row['past']);
    }

    public function isEventFull(int $eventId): bool
    {
        $stmt = $this->database->connect()->prepare('SELECT max_players FROM events WHERE id = ?');
        $stmt->execute([$eventId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $max = (int)($row['max_players'] ?? 0);
        $stmt2 = $this->database->connect()->prepare('SELECT COUNT(*) AS c FROM event_participants WHERE event_id = ?');
        $stmt2->execute([$eventId]);
        $c = (int)($stmt2->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
        return $max > 0 && $c >= $max;
    }

    public function joinEvent(int $userId, int $eventId): bool
    {
        try {
            $this->setAuditUser($userId);
            $ins = $this->database->connect()->prepare('INSERT INTO event_participants(event_id, user_id) VALUES(?, ?)');
            return $ins->execute([$eventId, $userId]);
        } catch (Throwable $e) {
            return false;
        }
    }

    public function isUserParticipant(int $userId, int $eventId): bool
    {
        $stmt = $this->database->connect()->prepare('SELECT 1 FROM event_participants WHERE event_id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$eventId, $userId]);
        return $stmt->fetch() !== false;
    }

    public function cancelParticipation(int $userId, int $eventId): bool
    {
        $this->setAuditUser($userId);
        $stmt = $this->database->connect()->prepare('DELETE FROM event_participants WHERE event_id = ? AND user_id = ?');
        $stmt->execute([$eventId, $userId]);
        return $stmt->rowCount() > 0;
    }

    public function participantsCount(int $eventId): int
    {
        $stmt = $this->database->connect()->prepare('SELECT COUNT(*) AS c FROM event_participants WHERE event_id = ?');
        $stmt->execute([$eventId]);
        return (int)($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
    }

    public function createEvent(array $data): ?int
    {
        try {
            $this->database->connect()->beginTransaction();

            $sql = "
                INSERT INTO events (owner_id, title, description, sport_id, location_text, latitude, longitude,
                                    start_time, level_id, image_url, max_players, min_needed)
                VALUES (:owner_id, :title, :description, :sport_id, :location_text, :latitude, :longitude,
                        :start_time, :level_id, :image_url, :max_players, :min_needed)
            ";
            $stmt = $this->database->connect()->prepare($sql);
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

            if (!$result) {
                $this->database->connect()->rollBack();
                return null;
            }

            $eventId = (int)$this->database->connect()->lastInsertId();

            $this->database->connect()->commit();
            return $eventId;
        } catch (Throwable $e) {
            $this->database->connect()->rollBack();
            error_log("Create event transaction error: " . $e->getMessage());
            return null;
        }
    }

    public function updateEvent(int $eventId, array $data): bool
    {
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

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE events SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->database->connect()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public function getEventsFromView(?int $limit = null): array
    {
        $sql = "SELECT * FROM vw_events_full";
        if ($limit) {
            $sql .= " LIMIT :limit";
        }
        $stmt = $this->database->connect()->prepare($sql);
        if ($limit) {
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function joinEventWithTransaction(int $userId, int $eventId): bool
    {
        try {
            $this->database->connect()->beginTransaction();

            $this->setAuditUser($userId);

            // Check if event exists and is not full
            if ($this->isEventFull($eventId)) {
                $this->database->connect()->rollBack();
                return false;
            }

            // Check if event is in the past
            if ($this->isEventPast($eventId)) {
                $this->database->connect()->rollBack();
                return false;
            }

            // Add user to event
            $ins = $this->database->connect()->prepare('INSERT INTO event_participants(event_id, user_id) VALUES(?, ?)');
            $ins->execute([$eventId, $userId]);

            $this->database->connect()->commit();
            return true;
        } catch (Throwable $e) {
            $this->database->connect()->rollBack();
            error_log("Transaction error: " . $e->getMessage());
            return false;
        }
    }


    public function cancelParticipationWithTransaction(int $userId, int $eventId): bool
    {
        try {
            $this->database->connect()->beginTransaction();

            $this->setAuditUser($userId);

            $stmt = $this->database->connect()->prepare('DELETE FROM event_participants WHERE event_id = ? AND user_id = ?');
            $stmt->execute([$eventId, $userId]);
            $this->database->connect()->commit();
            return $stmt->rowCount() > 0;
        } catch (Throwable $e) {
            $this->database->connect()->rollBack();
            error_log("Transaction error: " . $e->getMessage());
            return false;
        }
    }

    public function getFilteredEventsListing(array $filters, bool $isAdmin = false): array
    {
        $params = [];
        $conditions = [];

        if (!$isAdmin) {
            $conditions[] = "e.start_time >= NOW()";
        }

        if (!empty($filters['sports'])) {
            $placeholders = [];
            foreach ($filters['sports'] as $i => $name) {
                $key = ":sport" . $i;
                $placeholders[] = $key;
                $params[$key] = $name;
            }
            $conditions[] = "s.name IN (" . implode(',', $placeholders) . ")";
        }

        if (!empty($filters['level']) && $filters['level'] !== 'Any') {
            $conditions[] = "l.name = :level";
            $params[':level'] = $filters['level'];
        }

        $distanceField = "";
        $having = "";
        if (!empty($filters['center'])) {
            $lat = $filters['center']['lat'];
            $lng = $filters['center']['lng'];
            $haversine = "(6371 * acos(cos(radians(:lat)) * cos(radians(e.latitude)) * cos(radians(e.longitude) - radians(:lng)) + sin(radians(:lat)) * sin(radians(e.latitude))))";
            $distanceField = ", $haversine AS distance";
            $having = "HAVING $haversine <= :radius";
            $params[':lat'] = $lat;
            $params[':lng'] = $lng;
            $params[':radius'] = $filters['radius'];
        }

        $whereSql = $conditions ? "WHERE " . implode(" AND ", $conditions) : "";

        $sql = "
            SELECT e.*, s.name AS sport_name, l.name AS level_name, l.hex_color AS level_color, -- DODANE
                (SELECT COUNT(*) FROM event_participants p WHERE p.event_id = e.id) AS current_players
                $distanceField
            FROM events e
            LEFT JOIN sports s ON s.id = e.sport_id
            LEFT JOIN levels l ON l.id = e.level_id
            $whereSql
            GROUP BY e.id, s.name, l.name, l.hex_color -- POPRAWIONE: Grupowanie o hex_color
            $having
            ORDER BY e.start_time ASC
        ";

        $stmt = $this->database->connect()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getNearbyEvents(float $lat, float $lng, int $limit = 3): array
    {
        $sql = "
            SELECT 
                e.id, e.title, e.location_text, e.image_url, s.name as sport_name,
                (6371 * acos(GREATEST(-1, LEAST(1, cos(radians(:lat)) * cos(radians(e.latitude)) * cos(radians(e.longitude) - radians(:lng)) + sin(radians(:lat)) * sin(radians(e.latitude)))))) AS distance
            FROM events e
            LEFT JOIN sports s ON s.id = e.sport_id
            WHERE e.start_time >= NOW() AND e.latitude IS NOT NULL AND e.longitude IS NOT NULL
            ORDER BY distance ASC
            LIMIT :limit
        ";

        $stmt = $this->database->connect()->prepare($sql);
        $stmt->bindValue(':lat', $lat, PDO::PARAM_STR);
        $stmt->bindValue(':lng', $lng, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(function ($r) {
            return [
                'id' => $r['id'],
                'title' => $r['title'] ?? 'Event',
                'sport' => $r['sport_name'] ?? 'Sport',
                // TO MUSI SIĘ NAZYWAĆ TAK JAK W TWOIM HTML (linia ok. 95)
                'distanceText' => sprintf('%.1f km away', $r['distance']),
                'imageUrl' => $r['image_url'] ?? 'public/img/uploads/default.jpg',
                'cta' => 'See Details'
            ];
        }, $results);
    }
}
