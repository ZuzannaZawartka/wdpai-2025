<?php

require_once __DIR__ . '/Repository.php';

class UserRepository extends Repository{

    private $columnCache = null;
    
    public function __construct() {
        parent::__construct();
        $this->ensureColumns();
    }

    public function getUsers(): array {
        try {
            $query = $this->database->connect()->prepare('
                SELECT id, firstname, lastname, email, role, birth_date, latitude, longitude FROM users
            ');

            $query->execute();
            $users = $query->fetchAll(PDO::FETCH_ASSOC);

            return $users ?: [];
        } catch (Throwable $e) {
            error_log("getUsers error: " . $e->getMessage());
            // If query fails, try without role column
            try {
                $query = $this->database->connect()->prepare('
                    SELECT id, firstname, lastname, email, birth_date, latitude, longitude FROM users
                ');
                $query->execute();
                $users = $query->fetchAll(PDO::FETCH_ASSOC);
                
                // Add default role if not present
                foreach ($users as &$user) {
                    if (!isset($user['role'])) {
                        $user['role'] = 'basic';
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

    private function ensureColumns(): void {
        if ($this->columnCache !== null) {
            return; // Already cached
        }

        $conn = $this->database->connect();
        
        // Try to add missing columns
        $columns = ['birth_date', 'latitude', 'longitude', 'role'];
        foreach ($columns as $col) {
            try {
                if ($col === 'birth_date') {
                    $conn->exec("ALTER TABLE users ADD COLUMN $col DATE");
                } elseif ($col === 'role') {
                    $conn->exec("ALTER TABLE users ADD COLUMN $col VARCHAR(20) DEFAULT 'basic'");
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

    public function createUser(string $email, string $hashedPassword, string $firstname, string $lastname, ?string $birthDate = null, ?float $latitude = null, ?float $longitude = null, string $role = 'basic'): ?int {
        $this->ensureColumns();
        
        $query = $this->database->connect()->prepare('
            INSERT INTO users (firstname, lastname, email, password, birth_date, latitude, longitude, role)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
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
            $role
        ]);

        $row = $query->fetch(PDO::FETCH_ASSOC);
        return $row && isset($row['id']) ? (int)$row['id'] : null;
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

}