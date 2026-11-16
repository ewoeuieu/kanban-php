<?php

declare(strict_types=1);

namespace App\Http;

use App\Auth;
use JsonException;
use stdClass;

final class Router
{
    private array $routes = [];

    public function __construct(private readonly Auth $auth) {}

    public function add(string $method, string $path, AbstractRequest $action, bool $session = false, bool $private = false): void
    {
        $pattern = str_replace(preg_quote('{id}', '~'), '(?<id>[1-9][0-9]{0,18})', preg_quote($path, '~'));
        $this->routes[] = [$method, '~^' . $pattern . '$~D', $action, $session || $private, $private];
    }

    public function dispatch(string $method, string $path): Response
    {
        $allowed = [];
        foreach ($this->routes as [$registeredMethod, $pattern, $action, $session, $private]) {
            if (preg_match($pattern, $path, $matches) !== 1) {
                continue;
            }
            if ($method !== $registeredMethod && !($method === 'HEAD' && $registeredMethod === 'GET')) {
                $allowed[] = $registeredMethod;
                if ($registeredMethod === 'GET') {
                    $allowed[] = 'HEAD';
                }
                continue;
            }

            $id = isset($matches['id']) ? filter_var($matches['id'], FILTER_VALIDATE_INT) : null;
            if ($id === false) {
                throw new HttpError(404, 'Маршрут не найден.');
            }

            $input = in_array($method, ['POST', 'PATCH'], true) ? $this->jsonInput() : [];
            $user = null;
            if ($session) {
                $this->auth->start();
                $user = $this->auth->user();
            }
            if ($private && $user === null) {
                throw new HttpError(401, 'Войдите в аккаунт.');
            }

            return $action->execute($input, $user, $id);
        }

        if ($allowed !== []) {
            throw new HttpError(405, 'Метод не поддерживается.', [], ['Allow' => implode(', ', $allowed)]);
        }

        throw new HttpError(404, 'Маршрут не найден.');
    }

    private function jsonInput(): array
    {
        $contentType = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '', 2)[0]));
        if ($contentType !== 'application/json') {
            throw new HttpError(415, 'Укажите Content-Type: application/json.');
        }

        $body = file_get_contents('php://input', false, null, 0, 65537);
        if ($body === false || strlen($body) > 65536) {
            throw new HttpError(413, 'Слишком большой запрос.');
        }

        try {
            $object = json_decode($body, false, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new HttpError(400, 'Некорректный JSON.');
        }
        if (!$object instanceof stdClass) {
            throw new HttpError(400, 'Ожидается JSON-объект.');
        }

        return (array) $object;
    }
}
