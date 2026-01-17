<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);

require_once 'Routing.php';

$path = trim($_SERVER['REQUEST_URI'], '/');
$path = parse_url($path, PHP_URL_PATH);

Routing::run($path);
