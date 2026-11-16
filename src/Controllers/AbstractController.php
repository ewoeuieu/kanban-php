<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\AbstractRequest;
use App\Http\ActionRequest;
use App\Http\Router;
use Closure;

abstract class AbstractController
{
    abstract public function routes(Router $router): void;

    final protected function action(Closure $handler, array $parameters = [], ?Closure $validate = null): AbstractRequest
    {
        return new ActionRequest($parameters, $handler, $validate);
    }
}
