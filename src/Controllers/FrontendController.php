<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Http\HttpError;
use App\Http\Parameter;
use App\Http\Response;
use App\Http\Router;
use App\Models\MigrationProbe;
use App\Models\User;

final class FrontendController extends AbstractController
{
    public function __construct(
        private readonly Auth $auth,
        private readonly string $message,
    ) {}

    public function routes(Router $router): void
    {
        $index = $this->action($this->index(...));
        $router->add('GET', '/', $index);
        $router->add('GET', '/index.php', $index);
        $router->add('GET', '/health', $this->action($this->health(...)));
        $router->add('POST', '/register', $this->action(
            $this->register(...),
            ['email' => Parameter::email(), 'name' => Parameter::text(100), 'password' => Parameter::password(true)],
            $this->requireGuest(...),
        ), session: true);
        $router->add('POST', '/login', $this->action(
            $this->login(...),
            ['email' => Parameter::email(), 'password' => Parameter::password()],
            $this->requireGuest(...),
        ), session: true);
        $router->add('POST', '/logout', $this->action($this->logout(...)), private: true);
        $router->add('GET', '/me', $this->action($this->me(...)), private: true);
    }

    public function index(): Response
    {
        return Response::json([
            'application' => $this->message,
            'migration' => MigrationProbe::query()->value('message'),
        ]);
    }

    public function health(): Response
    {
        MigrationProbe::query()->firstOrFail();

        return Response::json(['status' => 'ok']);
    }

    public function register(array $input): Response
    {
        $user = $this->auth->register($input['email'], trim($input['name']), $input['password']);

        return Response::json(['user' => $user->toArray()], 201);
    }

    public function login(array $input): Response
    {
        $user = $this->auth->login($input['email'], $input['password']);

        return Response::json(['user' => $user->toArray()]);
    }

    public function logout(): Response
    {
        $this->auth->logout();

        return Response::empty();
    }

    public function me(array $input, User $user): Response
    {
        return Response::json(['user' => $user->toArray()]);
    }

    private function requireGuest(array $input, ?User $user): void
    {
        if ($user !== null) {
            throw new HttpError(409, 'Сначала выйдите из текущего аккаунта.');
        }
    }
}
