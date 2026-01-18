<?php
require_once __DIR__ . '/../services/Database.php';

class Repository
{
    protected \Database $database;

    protected static $instances = [];

    protected function __construct()
    {
        $this->database = Database::getInstance();
    }

    public static function getInstance(): static
    {
        $cls = static::class;
        if (!isset(self::$instances[$cls])) {
            self::$instances[$cls] = new static();
        }
        return self::$instances[$cls];
    }
}
