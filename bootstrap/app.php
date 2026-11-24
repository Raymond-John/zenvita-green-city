<?php

/**
 * Core PHP application bootstrap.
 * Laravel-inspired path only — this is not a Laravel application.
 */

define('BASE_PATH', dirname(__DIR__));

require_once BASE_PATH . '/app/Support/helpers.php';

return [
    'base_path' => BASE_PATH,
    'config' => [
        'app' => require BASE_PATH . '/config/app.php',
        'database' => require BASE_PATH . '/config/database.php',
        'mail' => require BASE_PATH . '/config/mail.php',
    ],
];
