<?php

declare(strict_types=1);

namespace App\Http;

use App\Models\User;

abstract class AbstractRequest
{
    /** @return array<string, Parameter> */
    abstract protected function parameters(): array;

    abstract protected function handle(array $input, ?User $user, ?int $id): Response;

    protected function validateLogic(array $input, ?User $user, ?int $id): void {}

    final public function execute(array $input, ?User $user, ?int $id): Response
    {
        $validated = [];
        $errors = [];
        foreach ($this->parameters() as $name => $parameter) {
            if (!array_key_exists($name, $input) && !$parameter->required) {
                $validated[$name] = $parameter->default;
            } elseif (!array_key_exists($name, $input) || !($parameter->accepts)($input[$name])) {
                $errors[$name] = $parameter->error;
            } else {
                $validated[$name] = $input[$name];
            }
        }
        if ($errors !== []) {
            throw new HttpError(422, 'Проверьте параметры запроса.', $errors);
        }

        $this->validateLogic($validated, $user, $id);

        return $this->handle($validated, $user, $id);
    }
}
