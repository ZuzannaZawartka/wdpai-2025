<?php

require_once __DIR__ . '/Repository.php';
require_once __DIR__ . '/../entity/User.php';

class UserRepository extends Repository
{

    public function __construct()
    {
        parent::__construct();
    }

    public function getUsers(): array
    {
        $query = $this->database->connect()->prepare('
            SELECT
                id,
                firstname,
                lastname,
                email,
                role,
                birth_date,
                latitude,
                longitude,
                avatar_url
            FROM users
        ');
        $query->execute();

        return $query->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getUserByEmail(string $email): ?array
    {
        $query = $this->database->connect()->prepare('
            SELECT * FROM users WHERE email = :email LIMIT 1
        ');
        $query->bindParam(':email', $email, PDO::PARAM_STR);
        $query->execute();

        return $query->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getUserForAuthByEmail(string $email): ?array
    {
        $query = $this->database->connect()->prepare('
            SELECT id, password, enabled, role
            FROM users
            WHERE email = :email
            LIMIT 1
        ');
        $query->bindParam(':email', $email, PDO::PARAM_STR);
        $query->execute();

        return $query->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getUserProfileById(int $id): ?array
    {
        $query = $this->database->connect()->prepare('
            SELECT id, firstname, lastname, email,
                birth_date, latitude, longitude,
                avatar_url, role, enabled
            FROM users
            WHERE id = :id
        ');
        $query->bindParam(':id', $id, PDO::PARAM_INT);
        $query->execute();

        return $query->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getUserById(int $id): ?array
    {
        $query = $this->database->connect()->prepare('
            SELECT * FROM users WHERE id = :id
        ');

        $query->bindParam(':id', $id, PDO::PARAM_INT);
        $query->execute();

        return $query->fetch(PDO::FETCH_ASSOC) ?: null;
    }


    public function getUserEntityById(int $id): ?\User
    {
        $row = $this->getUserById($id);
        if (!$row) return null;
        return new \User($row);
    }


    public function getUsersEntities(): array
    {
        $rows = $this->getUsers();
        return array_map(fn($r) => new \User($r), $rows);
    }


    public function getUserEntityByEmail(string $email): ?\User
    {
        $row = $this->getUserByEmail($email);
        if (!$row) return null;
        return new \User($row);
    }

    public function createUser(string $email, string $hashedPassword, string $firstname, string $lastname, ?string $birthDate = null, ?float $latitude = null, ?float $longitude = null, string $role = 'user', ?string $avatarUrl = null): ?int
    {

        $query = $this->database->connect()->prepare('
            INSERT INTO users (firstname, lastname, email, password, birth_date, latitude, longitude, role, avatar_url)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            RETURNING id
        ');

        $query->execute([
            $firstname,
            $lastname,
            $email,
            $hashedPassword,
            $birthDate,
            $latitude,
            $longitude,
            $role,
            $avatarUrl
        ]);

        $row = $query->fetch(PDO::FETCH_ASSOC);
        $userId = $row && isset($row['id']) ? (int)$row['id'] : null;

        // Inicjalizuj statystyki dla nowego użytkownika
        if ($userId) {
            $this->initializeUserStatistics($userId);
        }

        return $userId;
    }

    public function updateUser(string $email, array $data): bool
    {

        $fields = [];
        $params = [];

        if (isset($data['firstname'])) {
            $fields[] = 'firstname = ?';
            $params[] = $data['firstname'];
        }
        if (isset($data['lastname'])) {
            $fields[] = 'lastname = ?';
            $params[] = $data['lastname'];
        }
        if (array_key_exists('birth_date', $data)) {
            $fields[] = 'birth_date = ?';
            $params[] = $data['birth_date'];
        }
        if (array_key_exists('latitude', $data)) {
            $fields[] = 'latitude = ?';
            $params[] = $data['latitude'];
        }
        if (array_key_exists('longitude', $data)) {
            $fields[] = 'longitude = ?';
            $params[] = $data['longitude'];
        }
        if (array_key_exists('role', $data)) {
            $fields[] = 'role = ?';
            $params[] = $data['role'];
        }
        if (array_key_exists('avatar_url', $data)) {
            $fields[] = 'avatar_url = ?';
            $params[] = $data['avatar_url'];
        }

        if (empty($fields)) {
            return false;
        }

        $params[] = $email;
        $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE email = ?';

        $query = $this->database->connect()->prepare($sql);
        $query->execute($params);

        return $query->rowCount() > 0;
    }

    public function updateUserPassword(string $email, string $hashedPassword): void
    {
        $stmt = $this->database->connect()->prepare('
            UPDATE users SET password = :password WHERE email = :email
        ');
        $stmt->bindParam(':password', $hashedPassword, PDO::PARAM_STR);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->execute();
    }


    public function getUsersStatistics(): array
    {
        $query = $this->database->connect()->prepare('
            SELECT * FROM vw_user_stats ORDER BY full_name
        ');
        $query->execute();
        return $query->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }


    public function getUserStatisticsById(int $userId): ?array
    {
        $query = $this->database->connect()->prepare('
            SELECT * FROM vw_user_stats WHERE id = :id
        ');
        $query->bindParam(':id', $userId, PDO::PARAM_INT);
        $query->execute();
        return $query->fetch(PDO::FETCH_ASSOC) ?: null;
    }


    public function getUserAge(int $userId): ?int
    {
        $user = $this->getUserProfileById($userId);

        if (!$user || !isset($user['birth_date'])) {
            return null;
        }

        $query = $this->database->connect()->prepare('
            SELECT calculate_user_age(:birth_date) AS age
        ');
        $query->bindParam(':birth_date', $user['birth_date'], PDO::PARAM_STR);
        $query->execute();
        $result = $query->fetch(PDO::FETCH_ASSOC);
        return $result ? (int)$result['age'] : null;
    }


    public function initializeUserStatistics(int $userId): bool
    {
        try {
            $query = $this->database->connect()->prepare('
                INSERT INTO user_statistics (user_id, total_events_joined, total_events_created)
                VALUES (:user_id, 0, 0)
                ON CONFLICT (user_id) DO NOTHING
            ');
            $query->bindParam(':user_id', $userId, PDO::PARAM_INT);
            return $query->execute();
        } catch (Throwable $e) {
            error_log("Initialize statistics error: " . $e->getMessage());
            return false;
        }
    }


    public function getUserStatistics(int $userId): ?array
    {
        $query = $this->database->connect()->prepare('
            SELECT * FROM user_statistics WHERE user_id = :user_id
        ');
        $query->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $query->execute();
        return $query->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function emailExists(string $email): bool
    {
        $query = $this->database->connect()->prepare('
            SELECT 1 FROM users WHERE email = :email LIMIT 1
        ');

        $query->bindParam(':email', $email, PDO::PARAM_STR);
        $query->execute();

        return $query->fetchColumn() !== false;
    }
}
