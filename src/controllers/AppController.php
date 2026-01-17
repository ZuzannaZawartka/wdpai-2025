<?php
require_once __DIR__ . '/../../config/lang/lang_helper.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../repository/UserRepository.php';

class AppController
{

    protected static array $instances = [];

    protected function __construct() {}

    public function ensureSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
                || (isset($_SERVER['SERVER_PORT']) && (string)$_SERVER['SERVER_PORT'] === '443');

            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'domain' => '',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);

            session_start();

            if (empty($_SESSION['csrf_token'])) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }
        }
    }


    protected function isAuthenticated(): bool
    {
        $this->ensureSession();
        return isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id']);
    }

    public function getCurrentUserId(): ?int
    {
        $this->ensureSession();
        return isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    }

    protected function getCurrentUserEmail(): string
    {
        $this->ensureSession();
        return isset($_SESSION['user_email']) ? (string)$_SESSION['user_email'] : '';
    }

    public function requireAuth(): void
    {
        if (!$this->isAuthenticated()) {
            $this->respondUnauthorized();
        }
    }

    protected function setAuthContext(int $userId, string $email, string $role = 'user', ?string $avatar = null): void
    {
        $this->ensureSession();
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_role'] = $role;
        if (!empty($avatar)) {
            $_SESSION['user_avatar'] = $avatar;
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }

    protected function hashPassword(string $password): string
    {
        if (defined('PASSWORD_ARGON2ID')) {
            return password_hash($password, PASSWORD_ARGON2ID);
        }
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    protected function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    protected function isValidEmail(string $email): bool
    {
        $normalized = mb_strtolower(trim($email), 'UTF-8');

        if ($normalized === '' || mb_strlen($normalized, 'UTF-8') > 254) {
            return false;
        }
        return filter_var($normalized, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function getInstance(): static
    {
        $class = static::class;
        if (!isset(self::$instances[$class])) {
            self::$instances[$class] = new static();
        }
        return self::$instances[$class];
    }

    protected function isGet(): bool
    {
        return $_SERVER["REQUEST_METHOD"] === 'GET';
    }

    protected function isPost(): bool
    {
        return $_SERVER["REQUEST_METHOD"] === 'POST';
    }

    protected function isDelete(): bool
    {
        return $_SERVER["REQUEST_METHOD"] === 'DELETE';
    }

    protected function render(?string $template = null, array $variables = []): void
    {
        $this->ensureSession();

        // Dodaj avatar
        $variables['currentAvatar'] = $variables['currentAvatar'] ?? $this->getAvatarForCurrentUser();

        // Dodaj język jeśli nie ustawiony
        if (!isset($variables['lang']) && !empty($template)) {
            $variables['lang'] = get_lang($template);
        }

        $templatePath = 'public/views/' . $template . '.html';
        $templatePath404 = 'public/views/404.html';
        $output = '';

        // Wczytaj template lub 404
        $pathToInclude = file_exists($templatePath) ? $templatePath : $templatePath404;

        extract($variables);

        ob_start();
        include $pathToInclude;
        $output = ob_get_clean();

        echo $output;
    }

    protected function getAvatarForCurrentUser(): string
    {
        if (!empty($_SESSION['user_avatar'])) {
            return $_SESSION['user_avatar'];
        }

        $uid = $this->getCurrentUserId();
        if ($uid) {
            try {
                $repo = new UserRepository();
                $dbUser = $repo->getUserProfileById($uid);
                if (!empty($dbUser['avatar_url'])) {
                    $_SESSION['user_avatar'] = $dbUser['avatar_url'];
                    return $dbUser['avatar_url'];
                }
            } catch (Throwable $e) {
                // ignore i fallback
            }
        }

        return DEFAULT_AVATAR;
    }


    protected function hasRole(string $role): bool
    {
        return isset($_SESSION['user_id']) && ($_SESSION['user_role'] ?? null) === $role;
    }

    protected function hasRoles(...$roles): bool
    {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        $userRole = $_SESSION['user_role'] ?? null;
        return in_array($userRole, $roles, true);
    }

    protected function requireRole(string $role): void
    {
        if (!$this->hasRole($role)) {
            $this->respondForbidden();
        }
    }

    protected function requireRoles(...$roles): void
    {
        if (!$this->hasRoles(...$roles)) {
            $this->respondForbidden();
        }
    }

    protected function ensureAdmin(): bool
    {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }

        $role = $_SESSION['user_role'] ?? null;
        if ($role !== 'admin') {
            $this->setStatusCode(403);
            return false;
        }

        return true;
    }

    public function isAdmin(): bool
    {
        return isset($_SESSION['user_id']) && ($_SESSION['user_role'] ?? null) === 'admin';
    }

    protected function setStatusCode(int $code): void
    {
        if (!headers_sent()) {
            http_response_code($code);
        }
    }

    protected function isJsonRequest(): bool
    {
        return (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
            || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
    }

    protected function respondUnauthorized(?string $message = null, ?bool $json = null): void
    {
        $this->setStatusCode(401);
        $json = $json ?? $this->isJsonRequest();
        if ($json) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $message ?? 'Unauthorized']);
        } else {
            $this->redirect('/login');
        }
        exit();
    }

    public function respondForbidden(?string $message = null, ?bool $json = null, ?string $template = '404'): void
    {
        $this->setStatusCode(403);
        $json = $json ?? $this->isJsonRequest();
        if ($json) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $message ?? 'Forbidden']);
        } elseif (!$this->isAuthenticated()) {
            $this->redirect('/login');
        } else {
            $this->render($template, ['messages' => $message, 'message' => $message]);
        }
        exit();
    }

    public function respondNotFound(?string $message = null, ?bool $json = null, ?string $template = '404'): void
    {
        $this->setStatusCode(404);
        $json = $json ?? $this->isJsonRequest();
        if ($json) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $message ?? 'Not found']);
        } elseif (!$this->isAuthenticated()) {
            $this->redirect('/login');
        } else {
            $this->render($template, ['messages' => $message, 'message' => $message]);
        }
        exit();
    }

    protected function respondBadRequest(?string $message = null, ?bool $json = null, ?string $template = '404'): void
    {
        $this->setStatusCode(400);
        $json = $json ?? $this->isJsonRequest();
        if ($json) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $message ?? 'Bad request']);
        } elseif (!$this->isAuthenticated()) {
            $this->redirect('/login');
        } else {
            $this->render($template, ['messages' => $message, 'message' => $message]);
        }
        exit();
    }

    protected function respondInternalError(?string $message = null, ?bool $json = null, ?string $template = '404'): void
    {
        $this->setStatusCode(500);
        $json = $json ?? $this->isJsonRequest();
        if ($json) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $message ?? 'Internal server error']);
        } elseif (!$this->isAuthenticated()) {
            $this->redirect('/login');
        } else {
            $this->render($template, ['messages' => $message, 'message' => $message]);
        }
        exit();
    }

    protected function respondMethodNotAllowed(?string $message = null, ?bool $json = null, ?string $template = '404'): void
    {
        $this->setStatusCode(405);
        $json = $json ?? $this->isJsonRequest();
        if ($json) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $message ?? 'Method not allowed']);
        } elseif (!$this->isAuthenticated()) {
            $this->redirect('/login');
        } else {
            $this->render($template, ['messages' => $message, 'message' => $message]);
        }
        exit();
    }

    protected function respondConflict(?string $message = null, ?bool $json = null, ?string $template = '404'): void
    {
        $this->setStatusCode(409);
        $json = $json ?? $this->isJsonRequest();
        if ($json) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $message ?? 'Conflict']);
        } elseif (!$this->isAuthenticated()) {
            $this->redirect('/login');
        } else {
            $this->render($template, ['messages' => $message, 'message' => $message]);
        }
        exit();
    }

    protected function respondTooManyRequests(?string $message = null, ?bool $json = null, ?string $template = '404'): void
    {
        $this->setStatusCode(429);
        $json = $json ?? $this->isJsonRequest();
        if ($json) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $message ?? 'Too many requests']);
        } elseif (!$this->isAuthenticated()) {
            $this->redirect('/login');
        } else {
            $this->render($template, ['messages' => $message, 'message' => $message]);
        }
        exit();
    }

    protected function respondOk(array $data = []): void
    {
        $this->setStatusCode(200);
        header('Content-Type: application/json');
        echo json_encode(array_merge(['status' => 'success'], $data));
        exit();
    }

    protected function mapEventData(array $event): array
    {
        $startTime = $event['start_time'] ?? null;
        $formattedDate = 'Unknown date';

        if ($startTime) {
            try {
                $formattedDate = (new DateTime($startTime))->format('D, M j, g:i A');
            } catch (Exception $e) {
                $formattedDate = 'Invalid date';
            }
        }

        return [
            'id'         => (int)($event['id'] ?? 0),
            'title'      => (string)($event['title'] ?? 'Untitled'),
            'datetime'   => $formattedDate,
            'players'    => (int)($event['current_players'] ?? 0) . " / " . (int)($event['max_players'] ?? 0),
            'level'      => $event['level_name'] ?? 'Intermediate',
            'imageUrl'   => !empty($event['image_url']) ? $event['image_url'] : '/public/images/boisko.png',
            'levelColor' => $event['level_color'] ?? '#9E9E9E'
        ];
    }

    protected function redirect(string $url, int $code = 303): void
    {
        header("Location: $url", true, $code);
        exit();
    }
}
