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


//TODO: musimy zapewnić że utworzony obiekt ma tylko jedną instancję (singleton)

//TODO: w przyszłosci mozemy np /dashboard lub /dashboard/1234 i ten endpoint wyciagnie element o wskazanym id
//za pomoca regexu
class Routing{

    public static $routes = [
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
            "auth" => true
        ],
        'dashboard' => [
            "controller" => 'DashboardController',
            "action" => 'index',
            "auth" => true
        ],
        'sports' => [
            "controller" => 'SportsController',
            "action" => 'index',
            "auth" => true
        ],
        'joined' => [
            "controller" => 'JoinedController',
            "action" => 'index',
            "auth" => true
        ],
        'my' => [
            "controller" => 'MyController',
            "action" => 'index',
            "auth" => true
        ],
        'create' => [
            "controller" => 'CreateController',
            "action" => 'index',
            "auth" => true
        ],
        'profile' => [
            "controller" => 'UserController',
            "action" => 'profile',
            "auth" => true
        ],
        'profile-favourites' => [
            "controller" => 'UserController',
            "action" => 'updateFavourites',
            "auth" => true
        ],
        'search-cards' => [
            "controller" => 'DashboardController',
            "action" => 'search',
            "auth" => true
        ],
        'event' => [
            "controller" => 'EventController',
            "action" => 'details',
            "auth" => true
        ],
        'event-join' => [
            "controller" => 'EventController',
            "action" => 'join',
            "auth" => true
        ],
        'event-cancel' => [
            "controller" => 'EventController',
            "action" => 'cancel',
            "auth" => true
        ],
        'edit' => [
            "controller" => 'EditController',
            "action" => 'edit',
            "auth" => true,
            "requiresOwnership" => 'event'
        ]
    ];

    public static function run(string $path){
        $path = trim($path, '/');
        $segments = explode('/', $path);
        
        $action = $segments[0] ?? '';
        
        $parameters = array_slice($segments, 1);
        
        switch($action){
            case 'user':
                if (empty($parameters)) {
                    include 'public/views/404.html';
                    return;
                }
                self::dispatch($action, [$parameters[0]]);
                break;
            case 'event':
                if (empty($parameters)) {
                    include 'public/views/404.html';
                    return;
                }
                self::dispatch($action, [$parameters[0]]);
                break;
            case 'event-join':
                if (empty($parameters)) {
                    include 'public/views/404.html';
                    return;
                }
                self::dispatch($action, [$parameters[0]]);
                break;
            case 'event-cancel':
                if (empty($parameters)) {
                    include 'public/views/404.html';
                    return;
                }
                self::dispatch($action, [$parameters[0]]);
                break;
            case 'edit':
                if (empty($parameters)) {
                    include 'public/views/404.html';
                    return;
                }
                // Check if this is edit/save or edit/{id}
                if ($parameters[0] === 'save' && !empty($parameters[1])) {
                    // edit/save/{id}
                    self::dispatch($action, ['save', $parameters[1]]);
                } else {
                    // edit/{id}
                    self::dispatch($action, [$parameters[0]]);
                }
                break;
            default:
                self::dispatch($action);
                break;
        }
    }

    private static function dispatch(string $action, array $parameters = []): void
    {
        if (!isset(self::$routes[$action])) {
            include 'public/views/404.html';
            echo "<h2>404</h2>";
            return;
        }

        $controllerClass = self::$routes[$action]['controller'];
        $method = self::$routes[$action]['action'];
        
        // Check if this is a save action
        if (!empty($parameters) && $parameters[0] === 'save') {
            $method = 'save';
            array_shift($parameters); // Remove 'save' from parameters
        }

        $controller = $controllerClass::getInstance();

        // Default: require auth unless route explicitly sets auth=false
        $requiresAuth = !array_key_exists('auth', self::$routes[$action]) || !empty(self::$routes[$action]['auth']);
        if ($requiresAuth) {
            $controller->requireAuth();
        }
        
        // Check ownership if required
        if (isset(self::$routes[$action]['requiresOwnership'])) {
            $resourceType = self::$routes[$action]['requiresOwnership'];
            $resourceId = !empty($parameters) ? (int)$parameters[0] : null;
            if ($resourceId) {
                self::checkOwnership($controller, $resourceId, $resourceType);
            }
        }
        
        call_user_func_array([$controller, $method], $parameters);
    }
    
    private static function checkOwnership($controller, int $resourceId, string $resourceType): void
    {
        require_once __DIR__ . '/src/repository/MockRepository.php';
        
        $userId = $controller->getCurrentUserId();
        if (!$userId) {
            http_response_code(403);
            include 'public/views/404.html';
            exit();
        }
        
        $isOwner = false;
        
        if ($resourceType === 'event') {
            $allEvents = MockRepository::events();
            foreach ($allEvents as $ev) {
                if ($ev['id'] == $resourceId && ($ev['ownerId'] ?? null) === $userId) {
                    $isOwner = true;
                    break;
                }
            }
        }
        // Tutaj w przyszłości możesz dodać inne typy: 'profile', 'comment', etc.
        
        if (!$isOwner) {
            http_response_code(403);
            include 'public/views/404.html';
            exit();
        }
    }
}