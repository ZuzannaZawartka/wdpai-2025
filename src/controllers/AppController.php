<?php
require_once __DIR__ . '/../../config/lang/lang_helper.php';

require_once __DIR__ . '/../repository/UserRepository.php';
require_once __DIR__ . '/../config/AppConfig.php';

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


    /**
     * Checks if user is authenticated
     * 
     * @return bool true if user is logged in
     */
    protected function isAuthenticated(): bool
    {
        $this->ensureSession();
        return isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id']);
    }

    /**
     * Gets current user ID from session
     * 
     * @return int|null User ID or null if not authenticated
     */
    public function getCurrentUserId(): ?int
    {
        $this->ensureSession();
        return isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    }

    /**
     * Gets current user email from session
     * 
     * @return string|null User email or null
     */
    protected function getCurrentUserEmail(): ?string
    {
        $this->ensureSession();
        return isset($_SESSION['user_email']) ? (string)$_SESSION['user_email'] : '';
    }

    /**
     * Requires user to be authenticated
     * Redirects to login if not authenticated
     */
    public function requireAuth(): void
    {
        if (!$this->isAuthenticated()) {
            $this->respondUnauthorized();
        }
    }

    /**
     * Sets authentication context in session
     * 
     * @param int $userId User ID
     * @param string $email User email
     * @param string $role User role (default: 'user')
     * @param string|null $avatar Avatar URL
     */
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

    /**
     * Hashes a given password using a secure algorithm.
     * 
     * @param string $password The plain-text password to hash.
     * @return string The hashed password.
     */
    protected function hashPassword(string $password): string
    {
        if (defined('PASSWORD_ARGON2ID')) {
            return password_hash($password, PASSWORD_ARGON2ID);
        }
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /**
     * Verifies if a plain-text password matches a given hash.
     * 
     * @param string $password The plain-text password.
     * @param string $hash The hashed password.
     * @return bool True if the password matches the hash, false otherwise.
     */
    protected function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Validates email format
     * 
     * @param string $email Email to validate
     * @return bool true if valid
     */
    protected function isValidEmail(string $email): bool
    {
        $normalized = mb_strtolower(trim($email), 'UTF-8');

        if ($normalized === '' || mb_strlen($normalized, 'UTF-8') > 254) {
            return false;
        }
        return filter_var($normalized, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Returns the singleton instance of the current class.
     * 
     * @return static The singleton instance.
     */
    public static function getInstance(): static
    {
        $class = static::class;
        if (!isset(self::$instances[$class])) {
            self::$instances[$class] = new static();
        }
        return self::$instances[$class];
    }

    /**
     * Checks if request is GET
     * 
     * @return bool true if GET request
     */
    protected function isGet(): bool
    {
        return $_SERVER["REQUEST_METHOD"] === 'GET';
    }

    /**
     * Checks if request is POST
     * 
     * @return bool true if POST request
     */
    protected function isPost(): bool
    {
        return $_SERVER["REQUEST_METHOD"] === 'POST';
    }

    /**
     * Checks if the current request method is DELETE.
     * 
     * @return bool True if the request method is DELETE, false otherwise.
     */
    protected function isDelete(): bool
    {
        return $_SERVER["REQUEST_METHOD"] === 'DELETE';
    }

    /**
     * Renders view template
     * 
     * @param string|null $template Template name
     * @param array $variables Data to pass to view
     */
    protected function render(?string $template = null, array $variables = []): void
    {
        $this->ensureSession();

        $variables['currentAvatar'] = $variables['currentAvatar'] ?? $this->getAvatarForCurrentUser();

        if (!isset($variables['lang']) && !empty($template)) {
            $variables['lang'] = get_lang($template);
        }

        $templatePath = 'public/views/' . $template . '.html';
        $templatePath404 = 'public/views/404.html';
        $output = '';

        $pathToInclude = file_exists($templatePath) ? $templatePath : $templatePath404;

        extract($variables);

        ob_start();
        include $pathToInclude;
        $output = ob_get_clean();

        echo $output;
    }

    /**
     * Retrieves the avatar URL for the current user.
     * Falls back to default avatar if not found in session or database.
     * 
     * @return string The URL of the user's avatar.
     */
    protected function getAvatarForCurrentUser(): string
    {
        if (!empty($_SESSION['user_avatar'])) {
            return $_SESSION['user_avatar'];
        }

        $uid = $this->getCurrentUserId();
        if ($uid) {
            try {
                $repo = UserRepository::getInstance();
                $dbUser = $repo->getUserProfileById($uid);
                if (!empty($dbUser['avatar_url'])) {
                    $_SESSION['user_avatar'] = $dbUser['avatar_url'];
                    return $dbUser['avatar_url'];
                }
            } catch (Throwable $e) {
            }
        }

        return \AppConfig::DEFAULT_USER_AVATAR;
    }

    /**
     * Checks if the authenticated user has a specific role.
     * 
     * @param string $role The role to check for.
     * @return bool True if the user has the specified role, false otherwise.
     */
    protected function hasRole(string $role): bool
    {
        return isset($_SESSION['user_id']) && ($_SESSION['user_role'] ?? null) === $role;
    }

    /**
     * Checks if the authenticated user has any of the specified roles.
     * 
     * @param string ...$roles A variable number of roles to check against.
     * @return bool True if the user has at least one of the specified roles, false otherwise.
     */
    protected function hasRoles(...$roles): bool
    {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        $userRole = $_SESSION['user_role'] ?? null;
        return in_array($userRole, $roles, true);
    }

    /**
     * Requires the authenticated user to have a specific role.
     * Responds with forbidden status if the user does not have the role.
     * 
     * @param string $role The role required.
     */
    protected function requireRole(string $role): void
    {
        if (!$this->hasRole($role)) {
            $this->respondForbidden();
        }
    }

    /**
     * Requires the authenticated user to have at least one of the specified roles.
     * Responds with forbidden status if the user does not have any of the roles.
     * 
     * @param string ...$roles A variable number of roles required.
     */
    protected function requireRoles(...$roles): void
    {
        if (!$this->hasRoles(...$roles)) {
            $this->respondForbidden();
        }
    }

    /**
     * Ensures the authenticated user has an 'admin' role.
     * 
     * @return bool True if the user is an admin, false otherwise (and sets 403 status).
     */
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

    /**
     * Checks if current user is admin
     * 
     * @return bool true if user has admin role
     */
    public function isAdmin(): bool
    {
        return isset($_SESSION['user_id']) && ($_SESSION['user_role'] ?? null) === 'admin';
    }

    /**
     * Sets HTTP response status code
     * 
     * @param int $code HTTP status code
     */
    protected function setStatusCode(int $code): void
    {
        if (!headers_sent()) {
            http_response_code($code);
        }
    }

    /**
     * Checks if request accepts JSON response
     * 
     * @return bool true if JSON request
     */
    protected function isJsonRequest(): bool
    {
        return (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
            || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
    }

    /**
     * Responds with 401 Unauthorized
     * 
     * @param string|null $message Error message
     * @param bool|null $json Force JSON response
     */
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

    /**
     * Responds with 403 Forbidden
     * 
     * @param string|null $message Error message
     * @param bool|null $json Force JSON response
     * @param string|null $template Template to render
     */
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

    /**
     * Responds with 404 Not Found
     * 
     * @param string|null $message Error message
     * @param bool|null $json Force JSON response
     * @param string|null $template Template to render
     */
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

    /**
     * Responds with 400 Bad Request
     * 
     * @param string|null $message Error message
     * @param bool|null $json Force JSON response
     * @param string|null $template Template to render
     */
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

    /**
     * Responds with 500 Internal Server Error
     * 
     * @param string|null $message Error message
     * @param bool|null $json Force JSON response
     * @param string|null $template Template to render
     */
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

    /**
     * Responds with 405 Method Not Allowed
     * 
     * @param string|null $message Error message
     * @param bool|null $json Force JSON response
     * @param string|null $template Template to render
     */
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

    /**
     * Responds with 409 Conflict
     * 
     * @param string|null $message Error message
     * @param bool|null $json Force JSON response
     * @param string|null $template Template to render
     */
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

    /**
     * Responds with 429 Too Many Requests
     * 
     * @param string|null $message Error message
     * @param bool|null $json Force JSON response
     * @param string|null $template Template to render
     */
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

    /**
     * Responds with 200 OK JSON
     * 
     * @param array $data Response data
     */
    protected function respondOk(array $data = []): void
    {
        $this->setStatusCode(200);
        header('Content-Type: application/json');
        echo json_encode(array_merge(['status' => 'success'], $data));
        exit();
    }

    /**
     * Maps event database array to view array
     * 
     * @param array $event Event data from database
     * @return array Formatted event data
     */
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
            'level'      => $event['level_name'] ?? \AppConfig::DEFAULT_LEVEL_NAME,
            'imageUrl'   => !empty($event['image_url']) ? $event['image_url'] : \AppConfig::DEFAULT_EVENT_IMAGE,
            'levelColor' => $event['level_color'] ?? \AppConfig::DEFAULT_LEVEL_COLOR
        ];
    }

    /**
     * Redirects to specified URL
     * 
     * @param string $url Target URL
     * @param int $code HTTP redirect code (default: 303)
     */
    protected function redirect(string $url, int $code = 303): void
    {
        header("Location: $url", true, $code);
        exit();
    }
}
