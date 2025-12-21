<?php

require_once 'AppController.php';

require_once __DIR__ . '/../repository/UserRepository.php';
require_once __DIR__ . '/../../config/lang/lang_helper.php';

class SecurityController extends AppController {

    private const MAX_EMAIL_LENGTH = 150;
    private const MIN_PASSWORD_LENGTH = 8;
    private const MAX_PASSWORD_LENGTH = 128;
    private const MAX_NAME_LENGTH = 100;
    private const MIN_NAME_LENGTH = 2;

    private UserRepository $userRepository;

    public function __construct() {
        $this->userRepository = new UserRepository();
    }

    public function login() {

        $this->ensureSession();
        // Enforce allowed methods
        if (!$this->isGet() && !$this->isPost()) {
            header('HTTP/1.1 405 Method Not Allowed');
            header('Allow: GET, POST');
            return $this->render("login");
        }
        if(!$this->isPost()){
            return $this->render("login");
        }
        
        // CSRF validation
        $csrf = $_POST['csrf_token'] ?? '';
        if (empty($csrf) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
            header('HTTP/1.1 403 Forbidden');
            return $this->render("login", ["messages" => "Sesja wygasła, odśwież stronę i spróbuj ponownie"]);
        }

        $email = mb_strtolower(trim($_POST['email'] ?? ''), 'UTF-8');
        $password = trim($_POST['password'] ?? '');

        // Basic server-side length validation to prevent oversized inputs
        if (mb_strlen($email, 'UTF-8') > self::MAX_EMAIL_LENGTH || mb_strlen($password, 'UTF-8') < self::MIN_PASSWORD_LENGTH || mb_strlen($password, 'UTF-8') > self::MAX_PASSWORD_LENGTH) {
            header('HTTP/1.1 400 Bad Request');
            return $this->render("login", ["messages" => "Email lub hasło niepoprawne"]);
        }

        // Basic email format check (still return generic message)
        if (!$this->isValidEmail($email)) {
            header('HTTP/1.1 400 Bad Request');
            return $this->render("login", ["messages" => "Email lub hasło niepoprawne"]);
        }

        try {
            $user = $this->userRepository->getUserByEmail($email);
        } catch (Throwable $e) {
            header('HTTP/1.1 500 Internal Server Error');
            return $this->render("login", ["messages" => "Wewnętrzny błąd serwera"]);
        }

        if(!$user){
            header('HTTP/1.1 401 Unauthorized');
            return $this->render("login", ["messages"=>"Email lub hasło niepoprawne"]);
        }


        if(!$this->verifyPassword($password, $user['password'])){
            header('HTTP/1.1 401 Unauthorized');
            return $this->render("login", ["messages"=>"Email lub hasło niepoprawne"]);
        }

        $this->setAuthContext((int)$user['id'], $user['email']);

        // Use 303 See Other to avoid re-POST on refresh
        header("Location: /dashboard", true, 303);
        exit();
    }


    public function register(){

        $this->ensureSession();
        // Enforce allowed methods
        if (!$this->isGet() && !$this->isPost()) {
            header('HTTP/1.1 405 Method Not Allowed');
            header('Allow: GET, POST');
            return $this->render("register");
        }
        if($this->isGet()){
            return $this->render("register");
        }
        // CSRF validation
        $csrf = $_POST['csrf_token'] ?? '';
        if (empty($csrf) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
            header('HTTP/1.1 403 Forbidden');
            return $this->render("register", ["messages" => "Sesja wygasła, odśwież stronę i spróbuj ponownie"]);
        }

        $email = mb_strtolower(trim($_POST['email'] ?? ''), 'UTF-8');
        $password = trim($_POST['password'] ?? '');
        $password2 = trim($_POST['password2'] ?? '');
        $firstname = trim($_POST['firstname'] ?? '');
        $lastname = trim($_POST['lastname'] ?? '');

        // Validation aligned with DB schema and security guidelines
        $errors = [];
        if (mb_strlen($firstname, 'UTF-8') < self::MIN_NAME_LENGTH || mb_strlen($firstname, 'UTF-8') > self::MAX_NAME_LENGTH) {
            $errors[] = "Imię musi mieć " . self::MIN_NAME_LENGTH . "–" . self::MAX_NAME_LENGTH . " znaków";
        }
        if (mb_strlen($lastname, 'UTF-8') < self::MIN_NAME_LENGTH || mb_strlen($lastname, 'UTF-8') > self::MAX_NAME_LENGTH) {
            $errors[] = "Nazwisko musi mieć " . self::MIN_NAME_LENGTH . "–" . self::MAX_NAME_LENGTH . " znaków";
        }
        if (mb_strlen($email, 'UTF-8') === 0 || mb_strlen($email, 'UTF-8') > self::MAX_EMAIL_LENGTH) {
            $errors[] = "Email musi mieć maksymalnie " . self::MAX_EMAIL_LENGTH . " znaków";
        }
        if (!$this->isValidEmail($email)) {
            $errors[] = "Niepoprawny format email";
        }
        if (mb_strlen($password, 'UTF-8') < self::MIN_PASSWORD_LENGTH || mb_strlen($password, 'UTF-8') > self::MAX_PASSWORD_LENGTH) {
            $errors[] = "Hasło musi mieć " . self::MIN_PASSWORD_LENGTH . "–" . self::MAX_PASSWORD_LENGTH . " znaków";
        }
        if ($password !== $password2) {
            $errors[] = "Hasła nie są identyczne";
        }
        $emailExists = false;
        try {
            if ($this->userRepository->getUserByEmail($email)) {
                // Neutral message to avoid user enumeration via register form
                $errors[] = "Nie można utworzyć konta z podanymi danymi";
                $emailExists = true;
            }
        } catch (Throwable $e) {
            header('HTTP/1.1 500 Internal Server Error');
            return $this->render("register", ["messages" => "Wewnętrzny błąd serwera"]);
        }

        if (!empty($errors)) {
            if ($emailExists) {
                header('HTTP/1.1 409 Conflict');
            } else {
                header('HTTP/1.1 400 Bad Request');
            }
            return $this->render("register", ["messages" => implode('<br>', $errors)]);
        }

        $hashedPassword = $this->hashPassword($password);

        try {
            $this->userRepository->createUser(
                $email,
                $hashedPassword,
                $firstname,
                $lastname
            );
        } catch (Throwable $e) {
            header('HTTP/1.1 500 Internal Server Error');
            return $this->render("register", ["messages" => "Wewnętrzny błąd serwera"]);
        }

        // Use 303 See Other to avoid re-POST on refresh
        header("Location: /login", true, 303);
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