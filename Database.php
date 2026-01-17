<?php

require_once "config.php";
// .env - skorzystać z .env
//Databse powinien byc singletonem - mozna potem go przeniesc do src -> services

class Database
{
    private $username;
    private $password;
    private $host;
    private $database;

    private static $instance = null;
    private $pdo;

    public function __construct()
    {
        $this->username = username;
        $this->password = password;
        $this->host = host;
        $this->database = database;
    }

    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function connect()
    {
        if ($this->pdo) {
            return $this->pdo;
        }

        try {
            $conn = new PDO(
                "pgsql:host=$this->host;port=5432;dbname=$this->database",
                $this->username,
                $this->password,
                ["sslmode"  => "prefer"]
            );

            // set the PDO error mode to exception
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            // enforce real prepared statements and sane defaults
            $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            $this->pdo = $conn;
            return $conn;
        } catch (PDOException $e) {
            die("Connection failed: " . $e->getMessage());
        }
    }

    public function disconnect($conn)
    {
        $conn = null;
    }
}
