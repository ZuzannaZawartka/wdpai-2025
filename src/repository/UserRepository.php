<?php

require_once __DIR__ . '/Repository.php';

class UserRepository extends Repository{

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

    public function createUser(string $email, string $password, string $firstname, string $lastname){

        $query = $this->database->connect()->prepare('
            INSERT INTO users (firstname, lastname, email, password, bio)
            VALUES (?,?,?,?,?)
        ');

        $query->execute([
           $firstname,
            $lastname,
            $email,
            $hashedPassword,
            $bio
        ]);

        $user = $query->fetch(PDO::FETCH_ASSOC);

        return $user;

    }

}

?>