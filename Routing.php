<?php

require_once 'src/controllers/SecurityController.php';
require_once 'src/controllers/UserController.php';
require_once 'src/controllers/DashboardController.php';
require_once 'src/controllers/SportsController.php';
require_once 'src/controllers/JoinedController.php';
require_once 'src/controllers/MyController.php';
require_once 'src/controllers/CreateController.php';
require_once 'src/controllers/EventController.php';
require_once 'src/controllers/EditController.php';
require_once 'src/controllers/AdminController.php';


//TODO: musimy zapewnić że utworzony obiekt ma tylko jedną instancję (singleton)

//TODO: w przyszłosci mozemy np /dashboard lub /dashboard/1234 i ten endpoint wyciagnie element o wskazanym id
//za pomoca regexu
class Routing{

    public static $routes = [
        'accounts' => [
            "controller" => 'AdminController',
            "action" => 'accounts',
            "auth" => true,
            "requiresRole" => 'admin'
        ],
        'login' => [
            "controller" => 'SecurityController',
            "action" => 'login',
            "auth" => false
        ],
        'register' => [
            "controller" => 'SecurityController',
            "action" => 'register',
            "auth" => false
        ],
        'logout' => [
            "controller" => 'SecurityController',
            "action" => 'logout',
            "auth" => true
        ],
        'user' => [
            "controller" => 'UserController',
            "action" => 'details',
            "auth" => true,
            "requiresRole" => 'user'
        ],
        'dashboard' => [
            "controller" => 'DashboardController',
            "action" => 'index',
            "auth" => true,
            "requiresRole" => 'user'
        ],
        'sports' => [
            "controller" => 'SportsController',
            "action" => 'index',
            "auth" => true
        ],
        'joined' => [
            "controller" => 'JoinedController',
            "action" => 'index',
            "auth" => true,
            "requiresRole" => 'user'
        ],
        'my' => [
            "controller" => 'MyController',
            "action" => 'index',
            "auth" => true,
            "requiresRole" => 'user'
        ],
        'create' => [
            "controller" => 'CreateController',
            "action" => 'index',
            "auth" => true,
            "requiresRole" => 'user'
        ],
        'profile' => [
            "controller" => 'UserController',
            "action" => 'profile',
            "auth" => true
        ],
        'profile-update' => [
            "controller" => 'UserController',
            "action" => 'updateProfile',
            "auth" => true
        ],
        'event' => [
            "controller" => 'EventController',
            "action" => 'details',
            "auth" => true,
            "requiresRole" => 'user'
        ],
        'event-join' => [
            "controller" => 'EventController',
            "action" => 'join',
            "auth" => true,
            "requiresRole" => 'user'
        ],
        'event-leave' => [
            "controller" => 'EventController',
            "action" => 'leave',
            "auth" => true
        ],
        'event-delete' => [
            "controller" => 'EventController',
            "action" => 'delete',
            "auth" => true
        ], 
        'edit' => [
            "controller" => 'EditController',
            "action" => 'edit',
            "auth" => true,
            "requiresOwnership" => 'event',
            "requiresRole" => 'user'
        ],
        'accounts-edit' => [
            "controller" => 'AdminController',
            "action" => 'editUser',
            "auth" => true,
            "requiresRole" => 'admin'
        ]
    ];

    public static function run(string $path){
        $path = trim($path, '/');
        $segments = explode('/', $path);
        
        $action = $segments[0] ?? '';
        
        $parameters = array_slice($segments, 1);
        
        switch(true){
            case ($action === 'accounts' && isset($parameters[0]) && $parameters[0] === 'edit' && isset($parameters[1]) && is_numeric($parameters[1])):
                $_GET['id'] = $parameters[1];
                self::dispatch('accounts-edit', [$parameters[1]]);
                break;
            case ($action === 'user'):
                if (empty($parameters)) {
                    include 'public/views/404.html';
                    return;
                }
                self::dispatch($action, [$parameters[0]]);
                break;
            case ($action === 'event'):
                if (empty($parameters)) {
                    include 'public/views/404.html';
                    return;
                }
                $eventId = $parameters[0];
                $eventAction = $parameters[1] ?? null;
                if ($eventAction === 'join') {
                    self::dispatch('event-join', [$eventId]);
                } elseif ($eventAction === 'delete') {
                    self::dispatch('event-delete', [$eventId]);
                } elseif ($eventAction === 'leave') {
                    self::dispatch('event-leave', [$eventId]);
                } else {
                    self::dispatch('event', [$eventId]);
                }
                break;
            case ($action === 'edit'):
                if (empty($parameters)) {
                    include 'public/views/404.html';
                    return;
                }
                $resourceType = $parameters[0];
                $resourceId = null;
                $actionParam = null;
                $isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
                if (is_numeric($resourceType)) {
                    $resourceId = $resourceType;
                    $actionParam = $parameters[1] ?? null;
                    $resourceType = 'event';
                } else {
                    $resourceId = $parameters[1] ?? null;
                    $actionParam = $parameters[2] ?? null;
                }
                if ($resourceType === 'event' && $resourceId) {
                    if ($isPost || $actionParam === 'save') {
                        self::dispatch('edit', ['save', $resourceId]);
                    } else {
                        self::dispatch('edit', [$resourceId]);
                    }
                } else {
                    include 'public/views/404.html';
                }
                break;
            default:
                self::dispatch($action);
                break;
        }
    }

    private static function dispatch(string $action, array $parameters = []): void
    {
        // Sesja jest zarządzana przez ensureSession() w AppController
        $isEventDelete = ($action === 'event-delete');
        if (!isset(self::$routes[$action])) {
            if ($isEventDelete) {
                header('Content-Type: application/json');
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Route not found for event-delete']);
            } else {
                include 'public/views/404.html';
                echo "<h2>404</h2>";
            }
            return;
        }

        $controllerClass = self::$routes[$action]['controller'];
        $method = self::$routes[$action]['action'];
        
        if (!empty($parameters) && $parameters[0] === 'save') {
            $method = 'save';
            array_shift($parameters);
        }

        $controller = $controllerClass::getInstance();

        $requiresAuth = !array_key_exists('auth', self::$routes[$action]) || !empty(self::$routes[$action]['auth']);
        if ($requiresAuth) {
            if ($isEventDelete && !isset($_SESSION['user_id'])) {
                header('Content-Type: application/json');
                http_response_code(401);
                echo json_encode(['status' => 'error', 'message' => 'Not authenticated: user_id missing in session']);
                exit();
            }
            $controller->requireAuth();
        }
        
        if (isset(self::$routes[$action]['requiresOwnership'])) {
            $resourceType = self::$routes[$action]['requiresOwnership'];
            $resourceId = !empty($parameters) ? (int)$parameters[0] : null;
            if ($resourceId) {
                self::checkOwnership($controller, $resourceId, $resourceType);
            }
        }
        
        if (isset(self::$routes[$action]['requiresRole'])) {
            $requiredRole = self::$routes[$action]['requiresRole'];
            if (($_SESSION['user_role'] ?? null) !== $requiredRole) {
                if ($isEventDelete) {
                    header('Content-Type: application/json');
                    http_response_code(403);
                    $actualRole = $_SESSION['user_role'] ?? null;
                    echo json_encode([
                        'status' => 'error',
                        'message' => 'Forbidden: requires role ' . $requiredRole . ', got ' . var_export($actualRole, true)
                    ]);
                    exit();
                } else {
                    http_response_code(403);
                    include 'public/views/404.html';
                    exit();
                }
            }
        }
        
        call_user_func_array([$controller, $method], $parameters);
    }
    
    private static function checkOwnership($controller, int $resourceId, string $resourceType): void
    {
        require_once __DIR__ . '/src/repository/EventRepository.php';
        
        if ($controller->isAdmin()) {
            return;
        }
        
        $userId = $controller->getCurrentUserId();
        if (!$userId) {
            http_response_code(403);
            include 'public/views/404.html';
            exit();
        }
        
        $isOwner = false;
        
        if ($resourceType === 'event') {
            $repo = new EventRepository();
            $event = $repo->getEventById($resourceId);
            if ($event && isset($event['owner_id']) && (int)$event['owner_id'] === (int)$userId) {
                $isOwner = true;
            }
        }
                
        if (!$isOwner) {
            http_response_code(403);
            include 'public/views/404.html';
            exit();
        }
    }
}