<?php

declare(strict_types=1);

namespace App;

use InvalidArgumentException;

final class Config
{
    public static function env(string $name, ?string $default = null): ?string
    {
        $value = getenv($name);

        return $value === false ? $default : $value;
    }

    public static function load(string $file): array
    {
        $environments = require $file;
        $environment = self::env('APP_ENV', 'local');
        if ($environment === 'defaults' || !array_key_exists($environment, $environments)) {
            throw new InvalidArgumentException('Неизвестное значение APP_ENV.');
        }

        $config = array_replace_recursive($environments['defaults'], $environments[$environment]);
        $secure = self::env('SESSION_SECURE');
        if ($secure !== null) {
            $config['session']['secure'] = filter_var($secure, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE)
                ?? throw new InvalidArgumentException('SESSION_SECURE должно быть логическим значением.');
        }

        $cipher = null;
        array_walk_recursive($config, static function (mixed &$value) use (&$cipher): void {
            if (is_string($value) && str_starts_with($value, SecretCipher::PREFIX)) {
                $cipher ??= new SecretCipher(self::env('APP_KEY', ''));
                $value = $cipher->decrypt($value);
            }
        });

        return $config;
    }
}
