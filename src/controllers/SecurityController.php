<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repository/UserRepository.php';
require_once __DIR__ . '/../repository/AuthRepository.php';
require_once __DIR__ . '/../repository/SportsRepository.php';

class SecurityController extends AppController {

    private const MAX_EMAIL_LENGTH = 150;
    private const MIN_PASSWORD_LENGTH = 8;
    private const MAX_PASSWORD_LENGTH = 128;
    private const MAX_NAME_LENGTH = 100;
    private const MIN_NAME_LENGTH = 2;
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOGIN_LOCK_SECONDS = 900; 
    private const LOGIN_WINDOW_SECONDS = 60;

    private UserRepository $userRepository;
    private AuthRepository $authRepository;
    private SportsRepository $sportsRepository;

    public function __construct() {
        parent::__construct();
        $this->userRepository = new UserRepository();
        $this->authRepository = new AuthRepository();
        $this->sportsRepository = new SportsRepository();
    }

    public function login() {
        $this->ensureSession();

        if (!$this->isPost()) {
            return $this->render("login");
        }

        if (!$this->checkCsrf()) {
            header('HTTP/1.1 403 Forbidden');
            return $this->render("login", ["messages" => "Sesja wygasła, odśwież stronę"]);
        }

        $ipHash = $this->getClientIpHash();
        if ($this->isIpLockedAndRespond($ipHash)) return;

        $email = mb_strtolower(trim($_POST['email'] ?? ''), 'UTF-8');
        $password = $_POST['password'] ?? '';

        if (!$this->isValidEmail($email) || mb_strlen($password) < self::MIN_PASSWORD_LENGTH) {
            $this->incrementIpLimiter($ipHash);
            $this->authRepository->logFailedLoginAttempt($email, $ipHash, 'invalid_email_or_password_format');
            return $this->render("login", ["messages" => "Email lub hasło niepoprawne"]);
        }

        try {
            $user = $this->userRepository->getUserByEmail($email);

            if (!$user || !password_verify($password, $user['password'])) {
                $this->incrementIpLimiter($ipHash);
                $this->authRepository->logFailedLoginAttempt($email, $ipHash, 'user_not_found_or_bad_password');
                return $this->render("login", ["messages" => "Email lub hasło niepoprawne"]);
            }

            if (!($user['enabled'] ?? true)) {
                return $this->render("login", ["messages" => "Konto jest zablokowane"]);
            }

            $this->setAuthContext((int)$user['id'], $user['email'], $user['role'], $user['avatar_url']);
            session_write_close();
            $url = ($user['role'] === 'admin') ? '/sports' : '/dashboard';
            header("Location: $url", true, 303);
            exit();

        } catch (Throwable $e) {
            error_log($e->getMessage());
            $this->authRepository->logFailedLoginAttempt($email, $ipHash, 'exception');
            return $this->render("login", ["messages" => "Wewnętrzny błąd serwera"]);
        }
    }

    public function register() {
        $this->ensureSession();
        $allSports = $this->sportsRepository->getAllSports();

        if ($this->isGet()) {
            return $this->render("register", ['allSports' => $allSports]);
        }

        if (!$this->checkCsrf()) {
            return $this->render("register", ["messages" => "Błąd sesji", 'allSports' => $allSports]);
        }

        $email = mb_strtolower(trim($_POST['email'] ?? ''), 'UTF-8');
        $errors = $this->validateRegisterInputs(
            $_POST['firstname'] ?? '',
            $_POST['lastname'] ?? '',
            $email,
            $_POST['password'] ?? '',
            $_POST['password2'] ?? ''
        );

        if (!isset($_POST['birth_date']) || empty($_POST['birth_date'])) {
            $errors[] = "Data urodzenia jest wymagana";
        }

        if (empty($errors)) {
            try {
                if ($this->userRepository->emailExists($email)) {
                    $errors[] = "Konto z tym adresem już istnieje";
                } else {
                    $newUserId = $this->userRepository->createUser(
                        $email,
                        password_hash($_POST['password'], PASSWORD_BCRYPT),
                        $_POST['firstname'],
                        $_POST['lastname'],
                        $_POST['birth_date'],
                        (float)$_POST['latitude'],
                        (float)$_POST['longitude'],
                        'user'
                    );

                    if ($newUserId) {
                        $favouriteSports = array_map('intval', $_POST['favourite_sports'] ?? []);
                        $this->sportsRepository->setFavouriteSports($newUserId, $favouriteSports);
                        header("Location: /login?registered=1", true, 303);
                        exit();
                    }
                }
            } catch (Throwable $e) {
                error_log($e->getMessage());
                $errors[] = "Błąd bazy danych";
            }
        }

        return $this->render("register", [
            "messages" => implode('<br>', $errors),
            'allSports' => $allSports
        ]);
    }

    public function logout(): void {
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

    private function checkCsrf(): bool {
        $token = $_POST['csrf_token'] ?? '';
        return !empty($token) && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    private function getClientIpHash(): string {
        return hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown');
    }

    private function incrementIpLimiter(string $ipHash): void {
        try {
            $this->authRepository->incrementIpWindow($ipHash, self::LOGIN_WINDOW_SECONDS, self::MAX_LOGIN_ATTEMPTS, self::LOGIN_LOCK_SECONDS);
        } catch (Throwable $e) {
            error_log("Rate limiter fallback triggered: " . $e->getMessage());
        }
    }

    private function isIpLockedAndRespond(string $ipHash): bool {
        try {
            $ipAttempt = $this->authRepository->getIpAttempts($ipHash);
            if ($ipAttempt && (int)$ipAttempt['lock_until'] > time()) {
                $retry = (int)$ipAttempt['lock_until'] - time();
                $mins = (int)ceil($retry / 60);
                header('HTTP/1.1 429 Too Many Requests');
                $this->render("login", ["messages" => "Zbyt wiele prób. Spróbuj za {$mins} min."]);
                return true;
            }
        } catch (Throwable $e) {
            error_log($e->getMessage());
        }
        return false;
    }

    private function validateRegisterInputs($fn, $ln, $em, $p1, $p2): array {
        $errors = [];
        if (mb_strlen($fn) < self::MIN_NAME_LENGTH || mb_strlen($fn) > self::MAX_NAME_LENGTH) $errors[] = "Błędne imię";
        if (mb_strlen($ln) < self::MIN_NAME_LENGTH || mb_strlen($ln) > self::MAX_NAME_LENGTH) $errors[] = "Błędne nazwisko";
        if (!$this->isValidEmail($em)) $errors[] = "Niepoprawny email";
        if (mb_strlen($p1) < self::MIN_PASSWORD_LENGTH) $errors[] = "Hasło za krótkie";
        if ($p1 !== $p2) $errors[] = "Hasła nie są zgodne";
        return $errors;
    }
}