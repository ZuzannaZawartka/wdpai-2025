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
            header('Location: /login');
            exit();
        }
    }

    protected function setAuthContext(int $userId, string $email, string $role = 'basic', ?string $avatar = null): void
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

    protected function render(string $template = null, array $variables = [])
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
                    if ($dbUser && !empty($dbUser['avatar'])) {
                        $avatar = $dbUser['avatar'];
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
            header('HTTP/1.1 403 Forbidden');
            $this->render('404');
            exit();
        }
    }
    
    protected function requireRoles(...$roles): void {
        if (!$this->hasRoles(...$roles)) {
            header('HTTP/1.1 403 Forbidden');
            $this->render('404');
            exit();
        }
    }
    
    protected function ensureAdmin(): bool {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        
        $role = $_SESSION['user_role'] ?? null;
        if ($role !== 'admin') {
            header('HTTP/1.1 403 Forbidden');
            return false;
        }
        
        return true;
    }
    
    public function isAdmin(): bool {
        return isset($_SESSION['user_id']) && ($_SESSION['user_role'] ?? null) === 'admin';
    }

}