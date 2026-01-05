<?php

require_once __DIR__ . '/Repository.php';

class UserRepository extends Repository{

    private $columnCache = null;

    public function getUsers(): ?array{
        $query = $this->database->connect()->prepare('
            SELECT * FROM users
        ');

        $query->execute();

        $users = $query->fetch(PDO::FETCH_ASSOC);

        return $users;
    }

    public function getUserByEmail(string $email){
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
        $columns = ['birth_date', 'latitude', 'longitude'];
        foreach ($columns as $col) {
            try {
                if ($col === 'birth_date') {
                    $conn->exec("ALTER TABLE users ADD COLUMN $col DATE");
                } else {
                    $conn->exec("ALTER TABLE users ADD COLUMN $col DECIMAL(9,6)");
                }
            } catch (Throwable $e) {
                // Column already exists, that's fine
            }
        }
        
        $this->columnCache = true;
    }

    public function createUser(string $email, string $hashedPassword, string $firstname, string $lastname, ?string $birthDate = null, ?float $latitude = null, ?float $longitude = null): ?int {
        $this->ensureColumns();
        
        $query = $this->database->connect()->prepare('
            INSERT INTO users (firstname, lastname, email, password, birth_date, latitude, longitude)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            RETURNING id
        ');

        $query->execute([
            $firstname,
            $lastname,
            $email,
            $hashedPassword,
            $birthDate,
            $latitude,
            $longitude
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
        
        if (empty($fields)) {
            return false;
        }
        
        $params[] = $email;
        $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE email = ?';
        
        $query = $this->database->connect()->prepare($sql);
        $query->execute($params);
        
        return $query->rowCount() > 0;
    }

}