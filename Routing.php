<?php

require_once 'src/controllers/SecurityController.php';
require_once 'src/controllers/UserController.php';
require_once 'src/controllers/DashboardController.php';


//TODO: musimy zapewnić że utworzony obiekt ma tylko jedną instancję (singleton)

//TODO: w przyszłosci mozemy np /dashboard lub /dashboard/1234 i ten endpoint wyciagnie element o wskazanym id
//za pomoca regexu
class Routing{

    public static $routes = [
        'login' => [
            "controller" => 'SecurityController',
            "action" => 'login'
        ],
        'register' => [
            "controller" => 'SecurityController',
            "action" => 'register'
        ],
        'user' => [
            "controller" => 'UserController',
            "action" => 'details'
        ],
        'dashboard' => [
            "controller" => 'DashboardController',
            "action" => 'index'
        ],
        'search-cards' => [
            "controller" => 'DashboardController',
            "action" => 'search'
        ]
    ];

    public static function run(string $path){
        $path = trim($path, '/');
        $segments = explode('/', $path);
        
        // Pobierz pierwszy segment (nazwa routingu)
        $action = $segments[0] ?? '';
        
        // Pobierz parametry (wszystko po pierwszym segmencie)
        $parameters = array_slice($segments, 1);
        
        switch($action){
            case 'dashboard':
                $controller = Routing::$routes[$action]['controller'];
                $method = Routing::$routes[$action]['action'];

                $controller = $controller::getInstance();
                $controller->$method();
                break;
            case 'register':
            case 'login':
                $controller = Routing::$routes[$action]['controller'];
                $method = Routing::$routes[$action]['action'];
                
                $controller = $controller::getInstance();
                $controller->$method();
                break;
            case 'user':
                if (empty($parameters)) {
                    include 'public/views/404.html';
                    return;
                }
                
                $id = $parameters[0];
                $controller = Routing::$routes[$action]['controller'];
                $method = Routing::$routes[$action]['action'];
            
                $controller = $controller::getInstance();
                $controller->$method($id);
                break;

            case 'search-cards':
                $controller = Routing::$routes[$action]['controller'];
                $method = Routing::$routes[$action]['action'];
                $controller = $controller::getInstance();
                $controller->$method();
                break;
            default:
                include 'public/views/404.html';
                echo "<h2>404</h2>";
                break;
        }
    }
}