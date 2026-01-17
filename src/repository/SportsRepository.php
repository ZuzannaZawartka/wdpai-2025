<?php

require_once __DIR__ . '/Repository.php';
require_once __DIR__ . '/../entity/Sport.php';
require_once __DIR__ . '/../config/AppConfig.php';

class SportsRepository extends Repository
{

    public function getAllSports(): array
    {
        $stmt = $this->database->connect()->prepare('SELECT id, name, icon FROM sports ORDER BY id');
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }


    public function getAllSportsEntities(): array
    {
        $rows = $this->getAllSports();
        return array_map(fn($r) => new \Sport($r), $rows);
    }

    public function getAllLevels(): array
    {
        $stmt = $this->database->connect()->prepare('SELECT id, name, hex_color FROM levels ORDER BY id');
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getFavouriteSportsIds(int $userId): array
    {
        $stmt = $this->database->connect()->prepare('SELECT sport_id FROM user_favourite_sports WHERE user_id = ?');
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_map(fn($r) => (int)$r['sport_id'], $rows);
    }

    public function getDetailedFavouriteSports(int $userId): array
    {
        $stmt = $this->database->connect()->prepare('
            SELECT s.id, s.name, s.icon 
            FROM sports s
            JOIN user_favourite_sports ufs ON s.id = ufs.sport_id
            WHERE ufs.user_id = ?
        ');
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_map(function ($row) {
            $row['icon'] = $row['icon'] ?: AppConfig::DEFAULT_SPORT_ICON;
            return $row;
        }, $rows);
    }

    public function setFavouriteSports(int $userId, array $sportIds): void
    {
        $conn = $this->database->connect();
        $conn->beginTransaction();
        try {
            $del = $conn->prepare('DELETE FROM user_favourite_sports WHERE user_id = ?');
            $del->execute([$userId]);
            if (!empty($sportIds)) {
                $ins = $conn->prepare('INSERT INTO user_favourite_sports(user_id, sport_id) VALUES(?, ?)');
                foreach ($sportIds as $sid) {
                    $ins->execute([$userId, (int)$sid]);
                }
            }
            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollBack();
            throw $e;
        }
    }
}
