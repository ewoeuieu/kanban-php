<?php

declare(strict_types=1);

use App\Config;

return [
    'defaults' => [
        'database' => [
            'driver' => 'pgsql',
            'host' => Config::env('DB_HOST', '127.0.0.1'),
            'port' => Config::env('DB_PORT', '5432'),
            'database' => Config::env('DB_NAME', 'kanban'),
            'username' => Config::env('DB_USER', 'kanban'),
            'password' => Config::env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
        ],
        'log_path' => Config::env('LOG_PATH', 'php://stderr'),
        'session' => [
            'ttl' => 86400,
            'secure' => false,
        ],
        'message' => 'Канбан-доска',
    ],
    'local' => [],
    'testing' => [
        'session' => ['ttl' => 900],
    ],
    'production' => [
        'session' => ['secure' => true],
    ],
];
