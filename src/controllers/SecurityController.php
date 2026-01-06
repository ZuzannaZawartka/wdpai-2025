<?php

require_once 'AppController.php';

require_once __DIR__ . '/../repository/UserRepository.php';
require_once __DIR__ . '/../repository/AuthRepository.php';
require_once __DIR__ . '/../repository/MockRepository.php';
require_once __DIR__ . '/../../config/lang/lang_helper.php';

class SecurityController extends AppController {

    private const MAX_EMAIL_LENGTH = 150;
    private const MIN_PASSWORD_LENGTH = 8;
    private const MAX_PASSWORD_LENGTH = 128;
    private const MAX_NAME_LENGTH = 100;
    private const MIN_NAME_LENGTH = 2;
    // Login rate limiting
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOGIN_LOCK_SECONDS = 15 * 60; // 15 minutes
    private const LOGIN_WINDOW_SECONDS = 60; // count attempts within 60s window

    private UserRepository $userRepository;
    private AuthRepository $authRepository;

    public function __construct() {
        $this->userRepository = new UserRepository();
        $this->authRepository = new AuthRepository();
    }

    public function login() {
        $this->ensureSession();
        if (!$this->isGet() && !$this->isPost()) {
            header('HTTP/1.1 405 Method Not Allowed');
            header('Allow: GET, POST');
            return $this->render("login");
        }
        if(!$this->isPost()){
            return $this->render("login");
        }

        $csrf = $_POST['csrf_token'] ?? '';
        if (empty($csrf) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
            header('HTTP/1.1 403 Forbidden');
            return $this->render("login", ["messages" => "Sesja wygasła, odśwież stronę i spróbuj ponownie"]);
        }

        $ipHash = $this->getClientIpHash();

        $email = mb_strtolower(trim($_POST['email'] ?? ''), 'UTF-8');
        $password = trim($_POST['password'] ?? '');

        // IP-only windowed limiter (with session fallback)
        $this->incrementIpLimiter($ipHash);

        // Check IP lock and respond if necessary
        if ($this->isIpLockedAndRespond($ipHash)) {
            return; // response already sent
        }

        // Basic input validation
        if (
            mb_strlen($email, 'UTF-8') > self::MAX_EMAIL_LENGTH ||
            mb_strlen($password, 'UTF-8') < self::MIN_PASSWORD_LENGTH ||
            mb_strlen($password, 'UTF-8') > self::MAX_PASSWORD_LENGTH ||
            !$this->isValidEmail($email)
        ) {
            header('HTTP/1.1 400 Bad Request');
            return $this->render("login", ["messages" => "Email lub hasło niepoprawne"]);
        }

        try {
            $user = $this->userRepository->getUserByEmail($email);
        } catch (Throwable $e) {
            header('HTTP/1.1 500 Internal Server Error');
            return $this->render("login", ["messages" => "Wewnętrzny błąd serwera"]);
        }

        if(!$user || !$this->verifyPassword($password, $user['password'])){
            header('HTTP/1.1 401 Unauthorized');
            return $this->render("login", ["messages"=>"Email lub hasło niepoprawne"]);
        }

        // Do not clear IP-level counters on success; they decay by window
        $avatar = $user['avatar'] ?? null;
        $this->setAuthContext((int)$user['id'], $user['email'], $user['role'] ?? 'basic', $avatar);
        header("Location: /dashboard", true, 303);
        exit();
    }


    public function register(){
        $this->ensureSession();
        if (!$this->isGet() && !$this->isPost()) {
            header('HTTP/1.1 405 Method Not Allowed');
            header('Allow: GET, POST');
            return $this->render("register");
        }
        if($this->isGet()){
            $allSports = array_values(MockRepository::sportsCatalog());
            return $this->render("register", ['allSports' => $allSports]);
        }

        $allSports = array_values(MockRepository::sportsCatalog());

        $csrf = $_POST['csrf_token'] ?? '';
        if (empty($csrf) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
            header('HTTP/1.1 403 Forbidden');
            return $this->render("register", ["messages" => "Sesja wygasła, odśwież stronę i spróbuj ponownie", 'allSports' => $allSports]);
        }

        $email = mb_strtolower(trim($_POST['email'] ?? ''), 'UTF-8');
        $password = trim($_POST['password'] ?? '');
        $password2 = trim($_POST['password2'] ?? '');
        $firstname = trim($_POST['firstname'] ?? '');
        $lastname = trim($_POST['lastname'] ?? '');
        $birth_date = trim($_POST['birth_date'] ?? '');
        $latitude = trim($_POST['latitude'] ?? '');
        $longitude = trim($_POST['longitude'] ?? '');

        $errors = $this->validateRegisterInputs($firstname, $lastname, $email, $password, $password2);

        $favouriteSports = array_map('intval', $_POST['favourite_sports'] ?? []);
        
        // Validate birth_date, latitude, longitude
        if (empty($birth_date)) {
            $errors[] = "Data urodzenia jest wymagana";
        } else {
            $birthTimestamp = strtotime($birth_date);
            if ($birthTimestamp === false || $birthTimestamp >= time()) {
                $errors[] = "Data urodzenia musi być w przeszłości i w poprawnym formacie";
            }
        }
        
        if (empty($latitude)) {
            $errors[] = "Lokalizacja jest wymagana - wybierz punkt na mapie";
        } elseif (!is_numeric($latitude) || (float)$latitude < -90 || (float)$latitude > 90) {
            $errors[] = "Szerokość geograficzna musi być między -90 a 90";
        }
        
        if (empty($longitude)) {
            $errors[] = "Lokalizacja jest wymagana - wybierz punkt na mapie";
        } elseif (!is_numeric($longitude) || (float)$longitude < -180 || (float)$longitude > 180) {
            $errors[] = "Długość geograficzna musi być między -180 a 180";
        }

        $emailExists = false;
        try {
            if ($this->userRepository->getUserByEmail($email)) {
                $errors[] = "Nie można utworzyć konta z podanymi danymi";
                $emailExists = true;
            }
        } catch (Throwable $e) {
            header('HTTP/1.1 500 Internal Server Error');
            return $this->render("register", ["messages" => "Wewnętrzny błąd serwera", 'allSports' => $allSports]);
        }

        if (!empty($errors)) {
            if ($emailExists) {
                header('HTTP/1.1 409 Conflict');
            } else {
                header('HTTP/1.1 400 Bad Request');
            }
            return $this->render("register", ["messages" => implode('<br>', $errors), 'allSports' => $allSports]);
        }

        $hashedPassword = $this->hashPassword($password);

        try {
            $newUserId = $this->userRepository->createUser(
                $email,
                $hashedPassword,
                $firstname,
                $lastname,
                $birth_date,
                (float)$latitude,
                (float)$longitude
            );

            if ($newUserId && !empty($favouriteSports)) {
                MockRepository::setUserFavouriteSports($newUserId, $favouriteSports);
            }
        } catch (Throwable $e) {
            error_log("Registration error: " . $e->getMessage());
            error_log("Trace: " . $e->getTraceAsString());
            header('HTTP/1.1 500 Internal Server Error');
            return $this->render("register", ["messages" => "Wewnętrzny błąd serwera: " . $e->getMessage(), 'allSports' => $allSports]);
        }

        header("Location: /login", true, 303);
        exit();
    }

    // Helpers
    private function getClientIpHash(): string
    {
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        return hash('sha256', $clientIp);
    }

    private function incrementIpLimiter(string $ipHash): void
    {
        try {
            $this->authRepository->incrementIpWindow($ipHash, self::LOGIN_WINDOW_SECONDS, self::MAX_LOGIN_ATTEMPTS, self::LOGIN_LOCK_SECONDS);
        } catch (Throwable $e) {
            $now = time();
            $last = (int)($_SESSION['ip_login_last_attempt'] ?? 0);
            $count = (int)($_SESSION['ip_login_attempts'] ?? 0);
            if ($now - $last <= self::LOGIN_WINDOW_SECONDS) {
                $count++;
            } else {
                $count = 1;
            }
            $_SESSION['ip_login_last_attempt'] = $now;
            if ($count >= self::MAX_LOGIN_ATTEMPTS) {
                $_SESSION['ip_login_lock_until'] = $now + self::LOGIN_LOCK_SECONDS;
                $_SESSION['ip_login_attempts'] = 0;
            } else {
                $_SESSION['ip_login_attempts'] = $count;
            }
        }
    }

    private function isIpLockedAndRespond(string $ipHash): bool
    {
        $now = time();
        $lockUntil = 0;
        try {
            $ipAttempt = $this->authRepository->getIpAttempts($ipHash);
        } catch (Throwable $e) {
            $ipAttempt = null;
        }
        if ($ipAttempt && isset($ipAttempt['lock_until'])) {
            $lockUntil = (int)$ipAttempt['lock_until'];
        } elseif ($ipAttempt === null) {
            // DB unavailable: consider session fallback
            $lockUntil = (int)($_SESSION['ip_login_lock_until'] ?? 0);
        }
        if ($lockUntil > $now) {
            $retry = $lockUntil - $now;
            $mins = max(1, (int)ceil($retry / 60));
            header('HTTP/1.1 429 Too Many Requests');
            header('Retry-After: ' . $retry);
            $this->render("login", ["messages" => "Zbyt wiele nieudanych prób. Spróbuj ponownie za {$mins} min."]);
            return true;
        }
        return false;
    }

    private function validateRegisterInputs(string $firstname, string $lastname, string $email, string $password, string $password2): array
    {
        $errors = [];
        if (mb_strlen($firstname, 'UTF-8') < self::MIN_NAME_LENGTH || mb_strlen($firstname, 'UTF-8') > self::MAX_NAME_LENGTH) {
            $errors[] = "Imię musi mieć " . self::MIN_NAME_LENGTH . "–" . self::MAX_NAME_LENGTH . " znaków";
        }
        if (mb_strlen($lastname, 'UTF-8') < self::MIN_NAME_LENGTH || mb_strlen($lastname, 'UTF-8') > self::MAX_NAME_LENGTH) {
            $errors[] = "Nazwisko musi mieć " . self::MIN_NAME_LENGTH . "–" . self::MAX_NAME_LENGTH . " znaków";
        }
        if (mb_strlen($email, 'UTF-8') > self::MAX_EMAIL_LENGTH || !$this->isValidEmail($email)) {
            $errors[] = "Nieprawidłowy adres email";
        }
        if (mb_strlen($password, 'UTF-8') < self::MIN_PASSWORD_LENGTH || mb_strlen($password, 'UTF-8') > self::MAX_PASSWORD_LENGTH) {
            $errors[] = "Hasło musi mieć " . self::MIN_PASSWORD_LENGTH . "–" . self::MAX_PASSWORD_LENGTH . " znaków";
        }
        if ($password !== $password2) {
            $errors[] = "Hasła muszą być identyczne";
        }
        return $errors;
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