<?php
require_once __DIR__ . '/../../Database.php';

class Repository{
    protected $database;

    public function __construct(){
        $this->database = new Database();
    }

    public function addUser($user)
    {
        $stmt = $this->database->connect()->prepare('
            INSERT INTO users (email, password, firstname, lastname)
            VALUES (?, ?, ?,?)
        ');

        $stmt->execute([
            $user['email'],
            $user['password'],
            $user['firstname'],
            $user['lastname']
        ]);

    }

}

