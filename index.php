<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/src/config/Env.php';
Env::load(__DIR__ . '/.env');

require_once 'Routing.php';

$path = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($path, PHP_URL_PATH);
$path = trim($path, '/');

Routing::run($path);
