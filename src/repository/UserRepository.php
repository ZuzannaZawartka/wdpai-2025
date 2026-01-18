<?php

require_once __DIR__ . '/Repository.php';
require_once __DIR__ . '/../entity/User.php';

class UserRepository extends Repository
{

    protected function __construct()
    {
        parent::__construct();
    }

    /**
     * Gets all users
     * 
     * @return array User data arrays
     */
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

    /**
     * Gets user by email
     * 
     * @param string $email User email
     * @return array|null User data or null
     */
    public function getUserByEmail(string $email): ?array
    {
        $query = $this->database->connect()->prepare('
            SELECT * FROM users WHERE email = :email LIMIT 1
        ');
        $query->bindParam(':email', $email, PDO::PARAM_STR);
        $query->execute();

        return $query->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Gets user credentials for authentication
     * 
     * @param string $email User email
     * @return array|null User with password hash
     */
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

    /**
     * Gets user profile by ID
     * 
     * @param int $id User ID
     * @return array|null Profile data
     */
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

    /**
     * Gets user by ID
     * 
     * @param int $id User ID
     * @return array|null User data
     */
    public function getUserById(int $id): ?array
    {
        $query = $this->database->connect()->prepare('
            SELECT * FROM users WHERE id = :id
        ');

        $query->bindParam(':id', $id, PDO::PARAM_INT);
        $query->execute();

        return $query->fetch(PDO::FETCH_ASSOC) ?: null;
    }


    /**
     * Gets user by ID as User entity
     * 
     * @param int $id User ID
     * @return User|null User object
     */
    public function getUserEntityById(int $id): ?\User
    {
        $row = $this->getUserById($id);
        if (!$row) return null;
        return new \User($row);
    }


    /**
     * Gets all users as User entities
     * 
     * @return array Array of User objects
     */
    public function getUsersEntities(): array
    {
        $rows = $this->getUsers();
        return array_map(fn($r) => new \User($r), $rows);
    }


    /**
     * Gets user by email as User entity
     * 
     * @param string $email User email
     * @return User|null User object or null
     */
    public function getUserEntityByEmail(string $email): ?\User
    {
        $row = $this->getUserByEmail($email);
        if (!$row) return null;
        return new \User($row);
    }

    /**
     * Creates new user
     * 
     * @param string $email User email
     * @param string $hashedPassword Password hash
     * @param string $firstname First name
     * @param string $lastname Last name
     * @param string|null $birthDate Birth date
     * @param float|null $latitude Location latitude
     * @param float|null $longitude Location longitude
     * @param string $role User role
     * @param string|null $avatarUrl Avatar URL
     * @return int|null New user ID or null
     */
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

        if ($userId) {
            $this->initializeUserStatistics($userId);
        }

        return $userId;
    }

    /**
     * Updates user data
     * 
     * @param string $email User email
     * @param array $data Fields to update
     * @return bool true if updated
     */
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

    /**
     * Updates user password
     * 
     * @param string $email User email
     * @param string $hashedPassword New password hash
     * @return bool true if updated
     */
    public function updateUserPassword(string $email, string $hashedPassword): bool
    {
        $stmt = $this->database->connect()->prepare('
            UPDATE users SET password = :password WHERE email = :email
        ');
        $stmt->bindParam(':password', $hashedPassword, PDO::PARAM_STR);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        return $stmt->execute();
    }


    /**
     * Gets statistics for all users
     * 
     * @return array User statistics data
     */
    public function getUsersStatistics(): array
    {
        $query = $this->database->connect()->prepare('
            SELECT * FROM vw_user_stats ORDER BY full_name
        ');
        $query->execute();
        return $query->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }


    /**
     * Gets statistics for specific user
     * 
     * @param int $userId User ID
     * @return array|null Statistics data
     */
    public function getUserStatisticsById(int $userId): ?array
    {
        $query = $this->database->connect()->prepare('
            SELECT * FROM vw_user_stats WHERE id = :id
        ');
        $query->bindParam(':id', $userId, PDO::PARAM_INT);
        $query->execute();
        return $query->fetch(PDO::FETCH_ASSOC) ?: null;
    }


    /**
     * Gets user age from birth date
     * 
     * @param int $userId User ID
     * @return int|null User age or null
     */
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


    /**
     * Initializes statistics for new user
     * 
     * @param int $userId User ID
     * @return bool true if initialized
     */
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


    /**
     * Gets user statistics from statistics table
     * 
     * @param int $userId User ID
     * @return array|null Statistics data or null
     */
    public function getUserStatistics(int $userId): ?array
    {
        $query = $this->database->connect()->prepare('
            SELECT * FROM user_statistics WHERE user_id = :user_id
        ');
        $query->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $query->execute();
        return $query->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Checks if email exists
     * 
     * @param string $email Email to check
     * @return bool true if email exists
     */
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
