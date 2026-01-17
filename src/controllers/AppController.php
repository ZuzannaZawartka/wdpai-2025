<?php
require_once __DIR__ . '/../../config/lang/lang_helper.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../repository/UserRepository.php';

class AppController {

    protected static array $instances = [];

    protected function __construct() {}

    protected function ensureSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            // Detect HTTPS natively or behind reverse proxy
            $secure = (
                (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
                (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') ||
                ((isset($_SERVER['SERVER_PORT']) && (string)$_SERVER['SERVER_PORT'] === '443'))
            );
            // If headers already sent (e.g., due to a prior warning), avoid changing cookie params
            if (!headers_sent()) {
                session_set_cookie_params([
                    'lifetime' => 0,
                    'path' => '/',
                    'domain' => '',
                    'secure' => $secure,
                    'httponly' => true,
                    //sameSite to 'Lax' to allow some cross-site requests 
                    'samesite' => 'Lax'
                ]);
            }
            if (!headers_sent()) {
                session_start();
            } else {
                // Attempt to start session even if headers were sent; may fail silently but avoids warnings
                @session_start();
            }
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

        $avatar = $variables['currentAvatar'] ?? null;
        if (empty($avatar)) {
            $avatar = $_SESSION['user_avatar'] ?? null;
        }
        if (empty($avatar)) {
            $uid = $this->getCurrentUserId();
            if ($uid) {
                try {
                    $repo = new UserRepository();
                    $dbUser = $repo->getUserById($uid);
                    if ($dbUser && !empty($dbUser['avatar_url'])) {
                        $avatar = $dbUser['avatar_url'];
                        $_SESSION['user_avatar'] = $avatar;
                    }
                } catch (Throwable $e) {
                    // ignore and fallback
                }
            }
        }
        if (empty($avatar)) {
            $avatar = DEFAULT_AVATAR;
        }
        $variables['currentAvatar'] = $avatar;

        $templatePath = 'public/views/'. $template.'.html';
        $templatePath404 = 'public/views/404.html';
        $output = "";
                 
        if(file_exists($templatePath)){
            if (!isset($variables['lang']) && is_string($template) && $template !== '') {
                $variables['lang'] = get_lang($template);
            }
            extract($variables);
            
            ob_start();
            include $templatePath;
            $output = ob_get_clean();
        } else {
            ob_start();
            include $templatePath404;
            $output = ob_get_clean();
        }
        echo $output;
    }
    
    protected function hasRole(string $role): bool {
        return isset($_SESSION['user_id']) && ($_SESSION['user_role'] ?? null) === $role;
    }
    
    protected function hasRoles(...$roles): bool {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        $userRole = $_SESSION['user_role'] ?? null;
        return in_array($userRole, $roles, true);
    }
    
    protected function requireRole(string $role): void {
        if (!$this->hasRole($role)) {
            $this->respondForbidden();
        }
    }
    
    protected function requireRoles(...$roles): void {
        if (!$this->hasRoles(...$roles)) {
            $this->respondForbidden();
        }
    }
    
    protected function ensureAdmin(): bool {
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
    
    public function isAdmin(): bool {
        return isset($_SESSION['user_id']) && ($_SESSION['user_role'] ?? null) === 'admin';
    }

    protected function setStatusCode(int $code): void {
        if (!headers_sent()) {
            http_response_code($code);
        }
    }
    
    protected function respondUnauthorized(?string $message = null, bool $json = false): void {
        $this->setStatusCode(401);
        if ($json) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $message ?? 'Unauthorized']);
        } else {
            header('Location: /login');
        }
        exit();
    }
    
    public function respondForbidden(?string $message = null, bool $json = false): void {
        $this->setStatusCode(403);
        if ($json) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $message ?? 'Forbidden']);
        } else {
            $this->render('404');
        }
        exit();
    }
    
    public function respondNotFound(?string $message = null, bool $json = false): void {
        $this->setStatusCode(404);
        if ($json) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $message ?? 'Not found']);
        } else {
            $this->render('404');
        }
        exit();
    }
    
    protected function respondBadRequest(?string $message = null, bool $json = false): void {
        $this->setStatusCode(400);
        if ($json) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $message ?? 'Bad request']);
        }
        exit();
    }
    
    protected function respondInternalError(?string $message = null, bool $json = false): void {
        $this->setStatusCode(500);
        if ($json) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $message ?? 'Internal server error']);
        }
        exit();
    }
    
    protected function respondMethodNotAllowed(?string $message = null, bool $json = false): void {
        $this->setStatusCode(405);
        if ($json) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $message ?? 'Method not allowed']);
        }
        exit();
    }
    
    protected function respondConflict(?string $message = null, bool $json = false): void {
        $this->setStatusCode(409);
        if ($json) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $message ?? 'Conflict']);
        }
        exit();
    }
    
    protected function respondTooManyRequests(?string $message = null, bool $json = false): void {
        $this->setStatusCode(429);
        if ($json) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $message ?? 'Too many requests']);
        }
        exit();
    }
    
    protected function respondOk(array $data = []): void {
        $this->setStatusCode(200);
        header('Content-Type: application/json');
        echo json_encode(array_merge(['status' => 'success'], $data));
        exit();
    }

}