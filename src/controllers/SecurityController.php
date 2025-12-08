<?php

require_once 'AppController.php';

require_once __DIR__ . '/../repository/UserRepository.php';

class SecurityController extends AppController {

    private UserRepository $userRepository;

    public function __construct() {
        $this->userRepository = new UserRepository();
    }

    public function login() {

    if(!$this->isPost()){
        return $this->render("login");
    }

    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    $user = $this->userRepository->getUserByEmail($email);

    if(!$user){
        return $this->render("login", ["messages"=>"Niepoprawny email lub hasło"]);
    }


    if(!password_verify($password, $user['password'])){
        return $this->render("login", ["messages"=>"Niepoprawny email lub hasło"]);
    }

    // Tu można ustawić sesję i przekierować np.: i dodac cookies i token
    // $_SESSION['user_id'] = $user['id'];
    header("Location: /dashboard");
    exit();
}


    public function register(){

        if($this->isGet()){
            return $this->render("register");
        }

        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $password2 = $_POST['password2'] ?? '';
        $firstname = $_POST['firstname'] ?? '';
        $lastname = $_POST['lastname'] ?? '';

        if($password !== $password2){
            return $this->render("register", ["messages"=>"Hasła nie są identyczne"]);
        }

        if($this->userRepository->getUserByEmail($email)){
            return $this->render("register", ["messages"=>"Użytkownik o podanym emailu już istnieje"]);
        }

        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        $this->userRepository->createUser(
            $email,
            $hashedPassword,
            $firstname,
            $lastname
        );


        header("Location: /login");
        exit();
    }

}