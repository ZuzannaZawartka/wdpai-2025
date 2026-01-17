<?php

class Database
{
    private string $username;
    private string $password;
    private string $host;
    private string $database;
    private int $port;

    private static ?Database $instance = null;
    private ?PDO $pdo = null;

    private function __construct()
    {
        $this->host = $_ENV['DB_HOST'] ?? (defined('host') ? constant('host') : 'db');
        $this->database = $_ENV['DB_NAME'] ?? (defined('database') ? constant('database') : 'db');
        $this->username = $_ENV['DB_USER'] ?? (defined('username') ? constant('username') : 'docker');
        $this->password = $_ENV['DB_PASSWORD'] ?? (defined('password') ? constant('password') : 'docker');
        $this->port = isset($_ENV['DB_PORT']) ? (int)$_ENV['DB_PORT'] : 5432;
    }

    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function connect(): PDO
    {
        if ($this->pdo) {
            return $this->pdo;
        }

        try {
            $dsn = "pgsql:host={$this->host};port={$this->port};dbname={$this->database}";

            $conn = new PDO(
                $dsn,
                $this->username,
                $this->password,
                ["sslmode" => "prefer"]
            );


            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            $this->pdo = $conn;
            return $conn;
        } catch (PDOException $e) {

            die("Connection failed: " . $e->getMessage());
        }
    }

    public function disconnect(): void
    {
        $this->pdo = null;
    }
}
