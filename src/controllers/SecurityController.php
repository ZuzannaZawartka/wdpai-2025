<?php

require_once 'AppController.php';

require_once __DIR__ . '/../repository/UserRepository.php';
require_once __DIR__ . '/../../config/lang/lang_helper.php';

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

        $this->setAuthContext((int)$user['id'], $user['email']);

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

        $hashedPassword = $this->hashPassword($password);

        $this->userRepository->createUser(
            $email,
            $hashedPassword,
            $firstname,
            $lastname
        );


        header("Location: /login");
        exit();
    }

    public function logout(): void
    {
        $this->ensureSession();

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }

        session_destroy();

        header("Location: /login");
        exit();
    }

}