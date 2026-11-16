<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Parameter;
use App\Http\Response;
use App\Http\Router;
use App\Kanban;
use App\Models\User;

final class BoardController extends AbstractController
{
    public function __construct(private readonly Kanban $kanban) {}

    public function routes(Router $router): void
    {
        $title = ['title' => Parameter::text(150)];
        $card = $title + ['description' => Parameter::text(5000, required: false)];
        $router->add('GET', '/boards', $this->action(
            fn (array $input, User $user) => Response::json(['boards' => $this->kanban->boards($user)]),
        ), private: true);
        $router->add('POST', '/boards', $this->action(
            fn (array $input, User $user) => Response::json(['board' => $this->kanban->createBoard($user, trim($input['title']))], 201),
            $title,
        ), private: true);
        $router->add('PATCH', '/boards/{id}', $this->action(
            fn (array $input, User $user, int $id) => Response::json(['board' => $this->kanban->editBoard($user, $id, trim($input['title']))]),
            $title,
        ), private: true);
        $router->add('DELETE', '/boards/{id}', $this->action(
            function (array $input, User $user, int $id): Response {
                $this->kanban->deleteBoard($user, $id);
                return Response::empty();
            },
        ), private: true);

        $router->add('GET', '/boards/{id}/columns', $this->action(
            fn (array $input, User $user, int $id) => Response::json(['columns' => $this->kanban->columns($user, $id)]),
        ), private: true);
        $router->add('POST', '/boards/{id}/columns', $this->action(
            fn (array $input, User $user, int $id) => Response::json(['column' => $this->kanban->createColumn($user, $id, trim($input['title']))], 201),
            $title,
        ), private: true);
        $router->add('PATCH', '/columns/{id}', $this->action(
            fn (array $input, User $user, int $id) => Response::json(['column' => $this->kanban->editColumn($user, $id, trim($input['title']))]),
            $title,
        ), private: true);
        $router->add('DELETE', '/columns/{id}', $this->action(
            function (array $input, User $user, int $id): Response {
                $this->kanban->deleteColumn($user, $id);
                return Response::empty();
            },
        ), private: true);

        $router->add('GET', '/boards/{id}/cards', $this->action(
            fn (array $input, User $user, int $id) => Response::json(['cards' => $this->kanban->cards($user, $id)]),
        ), private: true);
        $router->add('POST', '/columns/{id}/cards', $this->action(
            fn (array $input, User $user, int $id) => Response::json([
                'card' => $this->kanban->createCard($user, $id, trim($input['title']), $input['description'] ?? ''),
            ], 201),
            $card,
        ), private: true);
        $router->add('PATCH', '/cards/{id}', $this->action(
            fn (array $input, User $user, int $id) => Response::json([
                'card' => $this->kanban->editCard($user, $id, trim($input['title']), $input['description']),
            ]),
            $card,
        ), private: true);
        $router->add('POST', '/cards/{id}/move', $this->action(
            fn (array $input, User $user, int $id) => Response::json([
                'card' => $this->kanban->moveCard($user, $id, $input['axis'], $input['direction']),
            ]),
            ['axis' => Parameter::oneOf(['x', 'y']), 'direction' => Parameter::oneOf([-1, 1])],
        ), private: true);
        $router->add('POST', '/cards/{id}/archive', $this->action(
            fn (array $input, User $user, int $id) => Response::json(['card' => $this->kanban->archiveCard($user, $id)]),
        ), private: true);
        $router->add('DELETE', '/cards/{id}', $this->action(
            function (array $input, User $user, int $id): Response {
                $this->kanban->deleteCard($user, $id);
                return Response::empty();
            },
        ), private: true);
    }
}
