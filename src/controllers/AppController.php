<?php
require_once __DIR__ . '/../../config/lang/lang_helper.php';

class AppController {

    protected static array $instances = [];

    protected function __construct() {}

    // Ensures that the session is started with secure parameters
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

    // Checks if the user is authenticated
    protected function isAuthenticated(): bool
    {
        $this->ensureSession();
        return isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id']);
    }

    // Shared helpers to access current auth context
    protected function getCurrentUserId(): ?int
    {
        $this->ensureSession();
        return isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    }

    protected function getCurrentUserEmail(): string
    {
        $this->ensureSession();
        return isset($_SESSION['user_email']) ? (string)$_SESSION['user_email'] : '';
    }

    // Redirects to /login if user is not authenticated
    public function requireAuth(): void
    {
        if (!$this->isAuthenticated()) {
            header('Location: /login');
            exit();
        }
    }

    // Sets authentication context in the session
    protected function setAuthContext(int $userId, string $email): void
    {
        $this->ensureSession();
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_email'] = $email;
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }

    // Password hashing helper: prefers Argon2id if available, otherwise bcrypt
    protected function hashPassword(string $password): string
    {
        if (defined('PASSWORD_ARGON2ID')) {
            return password_hash($password, PASSWORD_ARGON2ID);
        }
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    // Password verification helper
    protected function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    // Email format validation helper (server-side)
    protected function isValidEmail(string $email): bool
    {
        // Normalize and validate
        $normalized = mb_strtolower(trim($email), 'UTF-8');
        // Basic length guard (align with typical limits, actual caps enforced in controller)
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

}