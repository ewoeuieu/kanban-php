<?php

declare(strict_types=1);

use App\Auth;
use App\Controllers\BoardController;
use App\Controllers\FrontendController;
use App\DatabaseSessionHandler;
use App\Http\HttpError;
use App\Http\Response;
use App\Http\Router;
use App\Kanban;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$path = explode('?', $_SERVER['REQUEST_URI'] ?? '/', 2)[0];
$start = hrtime(true);
$logger = new Logger('http');
$logPath = 'php://stderr';
$serverError = null;

try {
    require dirname(__DIR__) . '/bootstrap.php';
    $logPath = $config['log_path'];
    $connection = $database->getConnection();
    $auth = new Auth(new DatabaseSessionHandler($connection, $config['session']['ttl']), $config['session']);
    $router = new Router($auth);
    (new FrontendController($auth, $config['message']))->routes($router);
    (new BoardController(new Kanban($connection)))->routes($router);

    $response = $router->dispatch($method, $path);
} catch (HttpError $error) {
    $response = Response::json(
        ['error' => $error->getMessage()] + ($error->fields === [] ? [] : ['fields' => $error->fields]),
        $error->status,
        $error->headers,
    );
} catch (ModelNotFoundException $error) {
    $response = $path === '/health'
        ? Response::json(['status' => 'fail'], 503)
        : Response::json(['error' => 'Объект не найден.'], 404);
} catch (Throwable $error) {
    $serverError = $error::class;
    $response = $path === '/health'
        ? Response::json(['status' => 'fail'], 503)
        : Response::json(['error' => 'Внутренняя ошибка сервера.'], 500);
}

try {
    if (session_status() === PHP_SESSION_ACTIVE && !session_write_close()) {
        throw new RuntimeException('Не удалось сохранить сессию.');
    }
} catch (Throwable $error) {
    $serverError = $error::class;
    $response = Response::json(['error' => 'Не удалось сохранить сессию.'], 500);
}

$head = $method === 'HEAD';
$context = [
    'method' => $method,
    'path' => $path,
    'status' => $response->status,
    'duration_ms' => round((hrtime(true) - $start) / 1e6, 3),
    'response_body' => $head ? '' : $response->body,
];
if ($serverError !== null) {
    $context['error_type'] = $serverError;
}

try {
    $handler = new StreamHandler($logPath);
    $handler->setFormatter(new JsonFormatter());
    $logger->pushHandler($handler);
    $logger->log($response->status >= 500 ? 'error' : 'info', 'http.request', $context);
} catch (Throwable $error) {
    error_log('http logger failed: ' . $error::class);
}

$response->send($head);
