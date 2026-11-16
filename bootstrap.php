<?php

declare(strict_types=1);

use App\Config;
use Dotenv\Dotenv;
use Illuminate\Database\Capsule\Manager;

require_once __DIR__ . '/vendor/autoload.php';

Dotenv::createUnsafeImmutable(__DIR__)->safeLoad();
$config = Config::load(__DIR__ . '/config/app.php');

$database = new Manager();
$database->addConnection($config['database']);
$database->bootEloquent();
