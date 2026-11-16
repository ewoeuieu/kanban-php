<?php

declare(strict_types=1);

namespace App;

use InvalidArgumentException;
use RuntimeException;

final class SecretCipher
{
    public const PREFIX = 'enc:v1:';
    private const METHOD = 'aes-256-gcm';
    private const NONCE_BYTES = 12;
    private const TAG_BYTES = 16;
    private readonly string $key;

    public function __construct(string $encodedKey)
    {
        $key = base64_decode($encodedKey, true);
        if ($key === false || strlen($key) !== 32) {
            throw new InvalidArgumentException('APP_KEY должен содержать 32 байта в Base64.');
        }

        $this->key = $key;
    }

    public function encrypt(string $plainText): string
    {
        $nonce = random_bytes(self::NONCE_BYTES);
        $cipherText = openssl_encrypt(
            $plainText, self::METHOD, $this->key, OPENSSL_RAW_DATA, $nonce, $tag, self::PREFIX, self::TAG_BYTES,
        );
        if ($cipherText === false) {
            throw new RuntimeException('Не удалось зашифровать значение.');
        }

        return self::PREFIX . base64_encode($nonce . $tag . $cipherText);
    }

    public function decrypt(string $value): string
    {
        if (!str_starts_with($value, self::PREFIX)) {
            throw new InvalidArgumentException('Ожидается значение с префиксом enc:v1:.');
        }

        $data = base64_decode(substr($value, strlen(self::PREFIX)), true);
        if ($data === false || strlen($data) < self::NONCE_BYTES + self::TAG_BYTES) {
            throw new InvalidArgumentException('Повреждённое зашифрованное значение.');
        }

        $plainText = openssl_decrypt(
            substr($data, self::NONCE_BYTES + self::TAG_BYTES),
            self::METHOD,
            $this->key,
            OPENSSL_RAW_DATA,
            substr($data, 0, self::NONCE_BYTES),
            substr($data, self::NONCE_BYTES, self::TAG_BYTES),
            self::PREFIX,
        );
        if ($plainText === false) {
            throw new RuntimeException('Не удалось расшифровать значение с текущим APP_KEY.');
        }

        return $plainText;
    }
}
