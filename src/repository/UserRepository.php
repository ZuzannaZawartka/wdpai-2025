<?php

require_once __DIR__ . '/Repository.php';
require_once __DIR__ . '/../entity/User.php';

class UserRepository extends Repository{

    private $columnCache = null;
    
    public function __construct() {
        parent::__construct();
        $this->ensureColumns();
    }

    public function getUsers(): array {
        try {
            $query = $this->database->connect()->prepare('
                SELECT id, firstname, lastname, email, role, birth_date, latitude, longitude, avatar_url FROM users
            ');

            $query->execute();
            $users = $query->fetchAll(PDO::FETCH_ASSOC);

            return $users ?: [];
        } catch (Throwable $e) {
            error_log("getUsers error: " . $e->getMessage());
            // If query fails, try without role column
            try {
                $query = $this->database->connect()->prepare('
                    SELECT id, firstname, lastname, email, birth_date, latitude, longitude, avatar_url FROM users
                ');
                $query->execute();
                $users = $query->fetchAll(PDO::FETCH_ASSOC);
                
                // Add default role if not present
                foreach ($users as &$user) {
                    if (!isset($user['role'])) {
                        $user['role'] = 'user';
                    }
                }
                
                return $users ?: [];
            } catch (Throwable $e2) {
                error_log("getUsers fallback error: " . $e2->getMessage());
                return [];
            }
        }
    }
    
    public function getUserById(int $id): ?array {
        $query = $this->database->connect()->prepare('
            SELECT * FROM users WHERE id = :id
        ');
        
        $query->bindParam(':id', $id, PDO::PARAM_INT);
        $query->execute();
        
        return $query->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // New: return User entity or null
    public function getUserEntityById(int $id): ?\User
    {
        $row = $this->getUserById($id);
        if (!$row) return null;
        return new \User($row);
    }

    // New: return array of User entities
    public function getUsersEntities(): array
    {
        $rows = $this->getUsers();
        return array_map(fn($r) => new \User($r), $rows);
    }

    public function getUserByEmail(string $email){
        $this->ensureColumns();
        
        $query = $this->database->connect()->prepare('
            SELECT * FROM users WHERE email = :email
        ');

        $query->bindParam(':email', $email, PDO::PARAM_STR);
        $query->execute();

        $user = $query->fetch(PDO::FETCH_ASSOC);

        return $user;
    }

    // New: return User entity or null by email
    public function getUserEntityByEmail(string $email): ?\User
    {
        $row = $this->getUserByEmail($email);
        if (!$row) return null;
        return new \User($row);
    }

    private function ensureColumns(): void {
        if ($this->columnCache !== null) {
            return; // Already cached
        }

        $conn = $this->database->connect();
        
        // Try to add missing columns
        $columns = ['birth_date', 'latitude', 'longitude', 'role', 'avatar_url'];
        foreach ($columns as $col) {
            try {
                if ($col === 'birth_date') {
                    $conn->exec("ALTER TABLE users ADD COLUMN $col DATE");
                } elseif ($col === 'role') {
                    $conn->exec("ALTER TABLE users ADD COLUMN $col VARCHAR(20) DEFAULT 'user'");
                } elseif ($col === 'avatar_url') {
                    $conn->exec("ALTER TABLE users ADD COLUMN $col TEXT");
                } else {
                    $conn->exec("ALTER TABLE users ADD COLUMN $col DECIMAL(9,6)");
                }
            } catch (Throwable $e) {
                // Column already exists, that's fine
            }
        }
        
        // Create default admin if doesn't exist
        try {
            $adminEmail = 'admin@gmail.com';
            $adminCheck = $conn->prepare('SELECT id FROM users WHERE email = ?');
            $adminCheck->execute([$adminEmail]);
            $adminExists = $adminCheck->fetch(PDO::FETCH_ASSOC);
            
            if (!$adminExists) {
                // Use bcrypt for compatibility
                $adminPassword = password_hash('adminadmin', PASSWORD_BCRYPT, ['cost' => 12]);
                $stmt = $conn->prepare('
                    INSERT INTO users (email, password, firstname, lastname, role)
                    VALUES (?, ?, ?, ?, ?)
                ');
                $result = $stmt->execute([$adminEmail, $adminPassword, 'Admin', 'User', 'admin']);
                error_log("Admin account created: " . ($result ? 'success' : 'failed'));
            }
        } catch (Throwable $e) {
            error_log("Admin creation error: " . $e->getMessage());
        }
        
        $this->columnCache = true;
    }

    public function createUser(string $email, string $hashedPassword, string $firstname, string $lastname, ?string $birthDate = null, ?float $latitude = null, ?float $longitude = null, string $role = 'user', ?string $avatarUrl = null): ?int {
        $this->ensureColumns();
        
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

    public function updateUser(string $email, array $data): bool {
        $this->ensureColumns();
        
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

    public function updateUserPassword(string $email, string $hashedPassword): bool {
        error_log("Updating password for email: $email");
        error_log("New hash starts with: " . substr($hashedPassword, 0, 20));
        
        $query = $this->database->connect()->prepare('
            UPDATE users SET password = ? WHERE email = ?
        ');
        
        $result = $query->execute([$hashedPassword, $email]);
        $rowCount = $query->rowCount();
        
        error_log("Password update - executed: " . ($result ? 'true' : 'false') . ", rows affected: $rowCount");
        
        return $rowCount > 0;
    }

    // UŻYCIE WIDOKU: Pobiera statystyki użytkowników z widoku vw_user_stats
    public function getUsersStatistics(): array {
        $query = $this->database->connect()->prepare('
            SELECT * FROM vw_user_stats ORDER BY full_name
        ');
        $query->execute();
        return $query->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // UŻYCIE WIDOKU: Pobiera statystyki konkretnego użytkownika
    public function getUserStatisticsById(int $userId): ?array {
        $query = $this->database->connect()->prepare('
            SELECT * FROM vw_user_stats WHERE id = :id
        ');
        $query->bindParam(':id', $userId, PDO::PARAM_INT);
        $query->execute();
        return $query->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // UŻYCIE FUNKCJI: Oblicza wiek użytkownika
    public function getUserAge(int $userId): ?int {
        $user = $this->getUserById($userId);
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


    // UŻYCIE TABELI: Pobiera statystyki użytkownika
    public function getUserStatistics(int $userId): ?array {
        $query = $this->database->connect()->prepare('
            SELECT * FROM user_statistics WHERE user_id = :user_id
        ');
        $query->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $query->execute();
        return $query->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // Ensure a statistics row exists for a newly created user
    private function initializeUserStatistics(int $userId): void {
        try {
            $conn = $this->database->connect();
            $stmt = $conn->prepare(
                'INSERT INTO user_statistics (user_id, total_events_joined, total_events_created) VALUES (:user_id, 0, 0) ON CONFLICT (user_id) DO NOTHING'
            );
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
        } catch (Throwable $e) {
            error_log("initializeUserStatistics error: " . $e->getMessage());
        }
    }

}