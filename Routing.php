<?php

require_once 'src/controllers/SecurityController.php';
require_once 'src/controllers/UserController.php';
require_once 'src/controllers/DashboardController.php';
require_once 'src/controllers/SportsController.php';
require_once 'src/controllers/JoinedController.php';
require_once 'src/controllers/MyController.php';
require_once 'src/controllers/CreateController.php';


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
        'search-cards' => [
            "controller" => 'DashboardController',
            "action" => 'search',
            "auth" => true
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

        $controller = $controllerClass::getInstance();

        // Check authentication if required, and if it is, enforce it
        if (!empty(self::$routes[$action]['auth'])) {
            $controller->requireAuth();
        }
        call_user_func_array([$controller, $method], $parameters);
    }
}