<?php

require_once "config.php";
// .env - skorzystać z .env
//Databse powinien byc singletonem - mozna potem go przeniesc do src -> services

class Database {
    private $username;
    private $password;
    private $host;
    private $database;

    public function __construct()
    {
        $this->username = username;
        $this->password = password;
        $this->host = host;
        $this->database = database;
    }

    public function connect()
    {
        try {
            $conn = new PDO(
                "pgsql:host=$this->host;port=5432;dbname=$this->database",
                $this->username,
                $this->password,
                ["sslmode"  => "prefer"]
            );

            // set the PDO error mode to exception
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $conn;
        }
        catch(PDOException $e) {
            die("Connection failed: " . $e->getMessage());
        }
    }

    public function disconnect($conn)
    {
        $conn = null;
    }
}

?>