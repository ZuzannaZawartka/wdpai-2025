<?php

require_once __DIR__ . '/Repository.php';
require_once __DIR__ . '/../entity/Sport.php';
require_once __DIR__ . '/../config/AppConfig.php';

class SportsRepository extends Repository
{
    protected function __construct()
    {
        parent::__construct();
    }

    /**
     * Gets all sports
     * 
     * @return array Sport data arrays
     */
    public function getAllSports(): array
    {
        $stmt = $this->database->connect()->prepare('SELECT id, name, icon FROM sports ORDER BY id');
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }


    /**
     * Gets all sports as Sport entities
     * 
     * @return array Array of Sport objects
     */
    public function getAllSportsEntities(): array
    {
        $rows = $this->getAllSports();
        return array_map(fn($r) => new \Sport($r), $rows);
    }

    /**
     * Gets all skill levels
     * 
     * @return array Level data arrays
     */
    public function getAllLevels(): array
    {
        $stmt = $this->database->connect()->prepare('SELECT id, name, hex_color FROM levels ORDER BY id');
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Gets user's favorite sport IDs
     * 
     * @param int $userId User ID
     * @return array Array of sport IDs
     */
    public function getFavouriteSportsIds(int $userId): array
    {
        $stmt = $this->database->connect()->prepare('SELECT sport_id FROM user_favourite_sports WHERE user_id = ?');
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_map(fn($r) => (int)$r['sport_id'], $rows);
    }

    /**
     * Gets detailed favorite sports for user
     * 
     * @param int $userId User ID
     * @return array Sport data with icons
     */
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

    /**
     * Sets user's favorite sports
     * Replaces existing favorites
     * 
     * @param int $userId User ID
     * @param array $sportIds Array of sport IDs
     */
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
