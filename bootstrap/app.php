<?php

/**
 * Application bootstrap (Laravel-style entry for shared config).
 */
define('BASE_PATH', dirname(__DIR__));

return [
    'base_path' => BASE_PATH,
    'config' => [
        'app' => require BASE_PATH . '/config/app.php',
        'database' => require BASE_PATH . '/config/database.php',
    ],
];
