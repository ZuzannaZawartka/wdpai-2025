<?php

class Routing{
    public static function run(string $path){
        switch($path){
            case 'dashboard':
                include 'public/views/dashboard.html';
                echo "<h2>Dashboard</h2>";
                break;
            case 'login':
                include 'public/views/login.html';
                echo "<h2>Login</h2>";
                break;
            default:
                include 'public/views/404.html';
                echo "<h2>404</h2>";
                break;
        }
    }
}