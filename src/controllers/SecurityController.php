<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repository/UserRepository.php';
require_once __DIR__ . '/../repository/AuthRepository.php';
require_once __DIR__ . '/../repository/SportsRepository.php';
require_once __DIR__ . '/../entity/User.php';

class SecurityController extends AppController
{
    private const MAX_EMAIL_LENGTH = 150;
    private const MIN_PASSWORD_LENGTH = 8;
    private const MAX_PASSWORD_LENGTH = 128;
    private const MAX_NAME_LENGTH = 100;
    private const MIN_NAME_LENGTH = 2;
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOGIN_LOCK_SECONDS = 900;
    private const LOGIN_WINDOW_SECONDS = 60;

    private const ROLE_USER = 'user';
    private const ROLE_ADMIN = 'admin';
    private const CSRF_KEY = 'csrf_token';

    private UserRepository $userRepository;
    private AuthRepository $authRepository;
    private SportsRepository $sportsRepository;

    public function __construct()
    {
        parent::__construct();
        $this->userRepository = new UserRepository();
        $this->authRepository = new AuthRepository();
        $this->sportsRepository = new SportsRepository();
    }

    public function login()
    {
        $this->ensureSession();
        if (!$this->isPost()) {
            return $this->render('login');
        }

        if (!$this->checkCsrf()) {
            $this->respondForbidden('Sesja wygasła, odśwież stronę', null, 'login');
        }

        $ipHash = $this->getClientIpHash();
        if ($this->isIpLockedAndRespond($ipHash)) return;

        $email = mb_strtolower(trim($this->post('email')), 'UTF-8');
        $password = $this->post('password');

        if (!$this->validateLoginInputs($email, $password)) {
            $this->handleFailedLogin($email, $ipHash, 'invalid_email_or_password_format');
            return $this->render('login', ['messages' => 'Email lub hasło niepoprawne']);
        }

        try {
            $user = $this->userRepository->getUserByEmail($email);
            if (!$user || !password_verify($password, $user['password'])) {
                $this->handleFailedLogin($email, $ipHash, 'user_not_found_or_bad_password');
                return $this->render('login', ['messages' => 'Email lub hasło niepoprawne']);
            }

            if (!($user['enabled'] ?? true)) {
                return $this->render('login', ['messages' => 'Konto jest zablokowane']);
            }

            // Entity Usage for Session Context
            require_once __DIR__ . '/../entity/User.php';
            $userEntity = new \User($user);

            $this->setAuthContext(
                (int)$userEntity->getId(),
                $userEntity->getEmail(),
                $userEntity->getRole() ?? self::ROLE_USER,
                $userEntity->getAvatarUrl()
            );
            session_write_close();
            $this->redirect($userEntity->getRole() === self::ROLE_ADMIN ? '/sports' : '/dashboard');
        } catch (Throwable $e) {
            error_log($e->getMessage());
            $this->handleFailedLogin($email, $ipHash, 'exception');
            return $this->render('login', ['messages' => 'Wewnętrzny błąd serwera']);
        }
    }

    private function validateLoginInputs(string $email, string $password): bool
    {
        return $this->isValidEmail($email) && mb_strlen($password) >= self::MIN_PASSWORD_LENGTH;
    }

    private function handleFailedLogin(?string $email, string $ipHash, string $reason): void
    {
        $this->incrementIpLimiter($ipHash);
        $this->authRepository->logFailedLoginAttempt($email, $ipHash, $reason);
    }

    private function post(string $key, $default = ''): mixed
    {
        return $_POST[$key] ?? $default;
    }

    public function register()
    {
        $this->ensureSession();
        $allSports = $this->sportsRepository->getAllSports();

        if ($this->isGet()) {
            return $this->render('register', ['allSports' => $allSports]);
        }

        if (!$this->checkCsrf()) {
            return $this->render('register', ['messages' => 'Błąd sesji', 'allSports' => $allSports]);
        }

        $email = mb_strtolower(trim($this->post('email')), 'UTF-8');
        $errors = $this->validateRegisterInputs(
            $this->post('firstname'),
            $this->post('lastname'),
            $email,
            $this->post('password'),
            $this->post('password2')
        );

        if (empty($this->post('birth_date'))) {
            $errors[] = 'Data urodzenia jest wymagana';
        }

        if (empty($errors)) {
            try {
                if ($this->userRepository->emailExists($email)) {
                    $errors[] = 'Konto z tym adresem już istnieje';
                } else {
                    $newUserId = $this->createNewUser();
                    if ($newUserId) {
                        $this->assignFavouriteSports($newUserId, $this->post('favourite_sports', []));
                        $this->redirect('/login?registered=1');
                    }
                }
            } catch (Throwable $e) {
                error_log($e->getMessage());
                $errors[] = 'Błąd bazy danych';
            }
        }

        return $this->render('register', [
            'messages' => implode('<br>', $errors),
            'allSports' => $allSports
        ]);
    }

    private function validateRegisterInputs($fn, $ln, $em, $p1, $p2): array
    {
        $errors = [];
        if (mb_strlen($fn) < self::MIN_NAME_LENGTH || mb_strlen($fn) > self::MAX_NAME_LENGTH) $errors[] = 'Błędne imię';
        if (mb_strlen($ln) < self::MIN_NAME_LENGTH || mb_strlen($ln) > self::MAX_NAME_LENGTH) $errors[] = 'Błędne nazwisko';
        if (!$this->isValidEmail($em)) $errors[] = 'Niepoprawny email';
        if (mb_strlen($p1) < self::MIN_PASSWORD_LENGTH) $errors[] = 'Hasło za krótkie';
        if ($p1 !== $p2) $errors[] = 'Hasła nie są zgodne';
        return $errors;
    }

    private function createNewUser(): ?int
    {
        $location = $this->post('location');
        $lat = (float)$this->post('latitude', 0);
        $lng = (float)$this->post('longitude', 0);

        if ($location && strpos($location, ',') !== false) {
            $parts = explode(',', $location);
            if (count($parts) === 2) {
                $lat = (float)trim($parts[0]);
                $lng = (float)trim($parts[1]);
            }
        }

        return $this->userRepository->createUser(
            mb_strtolower(trim($this->post('email')), 'UTF-8'),
            password_hash($this->post('password'), PASSWORD_BCRYPT),
            $this->post('firstname'),
            $this->post('lastname'),
            $this->post('birth_date'),
            $lat,
            $lng,
            self::ROLE_USER
        );
    }

    private function assignFavouriteSports(int $userId, array $sportIds): void
    {
        $this->sportsRepository->setFavouriteSports($userId, array_map('intval', $sportIds));
    }

    public function logout(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION = [];

        setcookie(session_name(), '', time() - 3600, '/');

        session_destroy();

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        if (isset($_COOKIE['csrf_token'])) {
            setcookie('csrf_token', '', time() - 3600, '/');
        }
        if (isset($_COOKIE['remember_me'])) {
            setcookie('remember_me', '', time() - 3600, '/');
        }

        $this->redirect('/login');
    }

    private function checkCsrf(): bool
    {
        $token = $this->post(self::CSRF_KEY);
        return !empty($token) && isset($_SESSION[self::CSRF_KEY]) && hash_equals($_SESSION[self::CSRF_KEY], $token);
    }

    private function getClientIpHash(): string
    {
        return hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown');
    }

    private function incrementIpLimiter(string $ipHash): void
    {
        try {
            $this->authRepository->incrementIpWindow($ipHash, self::LOGIN_WINDOW_SECONDS, self::MAX_LOGIN_ATTEMPTS, self::LOGIN_LOCK_SECONDS);
        } catch (Throwable $e) {
            error_log("Rate limiter fallback triggered: " . $e->getMessage());
        }
    }

    private function isIpLockedAndRespond(string $ipHash): bool
    {
        try {
            $ipAttempt = $this->authRepository->getIpAttempts($ipHash);
            if ($ipAttempt && (int)$ipAttempt['lock_until'] > time()) {
                $retry = (int)$ipAttempt['lock_until'] - time();
                $mins = (int)ceil($retry / 60);
                $this->respondTooManyRequests("Zbyt wiele prób. Spróbuj za {$mins} min.", null, 'login');
                return true;
            }
        } catch (Throwable $e) {
            error_log($e->getMessage());
        }
        return false;
    }
}
