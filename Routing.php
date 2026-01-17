<?php

require_once 'src/controllers/SecurityController.php';
require_once 'src/controllers/UserController.php';
require_once 'src/controllers/DashboardController.php';
require_once 'src/controllers/SportsController.php';
require_once 'src/controllers/JoinedController.php';
require_once 'src/controllers/MyController.php';
require_once 'src/controllers/EventController.php';
require_once 'src/controllers/AdminController.php';

class Routing {

    public static array $routes = [
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
            "controller" => 'EventController',
            "action" => 'showCreateForm',
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
            "requiresRole" => ['user', 'admin']
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
        'event-edit' => [
            "controller" => 'EventController',
            "action" => 'showEditForm',
            "auth" => true,
            "requiresOwnership" => 'event',
            "requiresRole" => ['user', 'admin']
        ],
        'accounts-edit' => [
            "controller" => 'AdminController',
            "action" => 'editUser',
            "auth" => true,
            "requiresRole" => 'admin'
        ]
    ];

    public static function run(string $path) {
        $path = trim($path, '/');
        $segments = explode('/', $path);
        $action = $segments[0] ?? '';
        $parameters = array_slice($segments, 1);

        // Special routes: /accounts/edit/{id}
        if ($action === 'accounts' && $parameters[0] ?? null === 'edit' && is_numeric($parameters[1] ?? null)) {
            $_GET['id'] = $parameters[1];
            self::dispatch('accounts-edit', [$parameters[1]]);
            return;
        }

        // Special route: /user/{id}
        if ($action === 'user') {
            if (empty($parameters) || !is_numeric($parameters[0])) {
                self::render404();
                return;
            }
            self::dispatch($action, [$parameters[0]]);
            return;
        }

        // Special route: /event/*
        if ($action === 'event') {
            if (self::handleEventRoute($parameters)) return;
        }

        // Normal dispatch
        self::dispatch($action);
    }

    private static function handleEventRoute(array $parameters): bool {
        $eventId = $parameters[0] ?? null;

        // /event/edit/{id} or /event/edit/save/{id}
        if (($eventId ?? null) === 'edit' && is_numeric($parameters[1] ?? null)) {
            $id = (int)$parameters[1];
            $isSave = ($parameters[2] ?? null) === 'save' || $_SERVER['REQUEST_METHOD'] === 'POST';
            self::dispatch('event-edit', $isSave ? ['save', $id] : [$id]);
            return true;
        }

        // /event/{id}/join|leave|delete
        if (is_numeric($eventId) && isset($parameters[1])) {
            $map = [
                'join' => 'event-join',
                'leave' => 'event-leave',
                'delete' => 'event-delete'
            ];
            $subAction = $parameters[1];
            if (isset($map[$subAction])) {
                self::dispatch($map[$subAction], [(int)$eventId]);
                return true;
            }
            self::render404();
            return true;
        }

        // /event/{id}
        if (is_numeric($eventId)) {
            self::dispatch('event', [(int)$eventId]);
            return true;
        }

        // /event (no id)
        self::render404();
        return true;
    }

    private static function dispatch(string $action, array $parameters = []): void {
        if (!isset(self::$routes[$action])) {
            self::render404($action === 'event-delete');
            return;
        }

        $route = self::$routes[$action];
        $controllerClass = $route['controller'];
        $method = $route['action'];
        $controller = $controllerClass::getInstance();

        // Override for /event-edit save
        if (!empty($parameters) && $parameters[0] === 'save') {
            $method = 'updateEvent';
            array_shift($parameters);
        }

        // Auth
        if ($route['auth'] ?? false) {
            $controller->requireAuth();
        }

        // Ownership
        if (isset($route['requiresOwnership'])) {
            self::checkOwnership($controller, (int)($parameters[0] ?? 0), $route['requiresOwnership']);
        }

        // Role
        if (isset($route['requiresRole'])) {
            self::checkRole($route['requiresRole'], $controller, $action);
        }

        call_user_func_array([$controller, $method], $parameters);
    }

    private static function checkRole($requiredRole, $controller, string $action): void {
        $userRole = $_SESSION['user_role'] ?? null;
        $hasRole = is_array($requiredRole) ? in_array($userRole, $requiredRole, true) : $userRole === $requiredRole;

        if (!$hasRole) {
            if ($action === 'event-delete') {
                header('Content-Type: application/json');
                http_response_code(403);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Forbidden: requires role ' . (is_array($requiredRole) ? implode(',', $requiredRole) : $requiredRole) . ', got ' . var_export($userRole, true)
                ]);
                exit();
            }
            http_response_code(403);
            self::render404();
            exit();
        }
    }

    private static function checkOwnership($controller, int $resourceId, string $resourceType): void {
        require_once __DIR__ . '/src/repository/EventRepository.php';
        if ($controller->isAdmin()) return;

        $userId = $controller->getCurrentUserId();
        if (!$userId) {
            self::render403();
        }

        $isOwner = false;
        if ($resourceType === 'event') {
            $repo = new EventRepository();
            $event = $repo->getEventById($resourceId);
            if ($event && (int)$event['owner_id'] === $userId) {
                $isOwner = true;
            }
        }

        if (!$isOwner) self::render403();
    }

    private static function render404(bool $json = false): void {
        if ($json) {
            header('Content-Type: application/json');
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Route not found']);
        } else {
            http_response_code(404);
            include 'public/views/404.html';
        }
        exit();
    }

    private static function render403(): void {
        http_response_code(403);
        include 'public/views/404.html';
        exit();
    }
}
