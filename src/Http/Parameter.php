<?php

declare(strict_types=1);

namespace App\Http;

use Closure;

final class Parameter
{
    private function __construct(
        public readonly Closure $accepts,
        public readonly string $error,
        public readonly bool $required = true,
        public readonly mixed $default = null,
    ) {}

    public static function text(int $max, bool $required = true): self
    {
        return new self(
            static fn (mixed $value): bool => is_string($value)
                && !str_contains($value, "\0")
                && (!$required || trim($value) !== '')
                && mb_strlen($value) <= $max,
            "Ожидается текст длиной до {$max} символов.",
            $required,
        );
    }

    public static function email(): self
    {
        return new self(
            static fn (mixed $value): bool => is_string($value)
                && strlen($value) <= 254
                && filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'Проверьте адрес электронной почты.',
        );
    }

    public static function password(bool $registration = false): self
    {
        return new self(
            static fn (mixed $value): bool => is_string($value)
                && !str_contains($value, "\0")
                && mb_strlen($value) >= ($registration ? 8 : 1)
                && strlen($value) <= 72,
            $registration ? 'Пароль должен содержать минимум 8 символов и максимум 72 байта.' : 'Введите пароль.',
        );
    }

    public static function oneOf(array $values): self
    {
        return new self(
            static fn (mixed $value): bool => in_array($value, $values, true),
            'Допустимые значения: ' . implode(', ', $values) . '.',
        );
    }
}
