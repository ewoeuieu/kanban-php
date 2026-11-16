<?php

declare(strict_types=1);

namespace App\Http;

use App\Models\User;
use Closure;

final class ActionRequest extends AbstractRequest
{
    public function __construct(
        private readonly array $rules,
        private readonly Closure $action,
        private readonly ?Closure $logic = null,
    ) {}

    protected function parameters(): array
    {
        return $this->rules;
    }

    protected function validateLogic(array $input, ?User $user, ?int $id): void
    {
        if ($this->logic !== null) {
            ($this->logic)($input, $user, $id);
        }
    }

    protected function handle(array $input, ?User $user, ?int $id): Response
    {
        return ($this->action)($input, $user, $id);
    }
}
