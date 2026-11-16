<?php

declare(strict_types=1);

use App\DatabaseSessionHandler;
use App\SessionCleanup;

try {
    require dirname(__DIR__) . '/bootstrap.php';
    $connection = $database->getConnection();
    $sessions = new DatabaseSessionHandler($connection, $config['session']['ttl']);
    $body = (new SessionCleanup($connection, $sessions))->metrics();
    header('Content-Type: text/plain; version=0.0.4; charset=utf-8');
    echo $body;
} catch (Throwable $error) {
    error_log('session cleanup metrics: ' . $error::class);
    http_response_code(503);
}
