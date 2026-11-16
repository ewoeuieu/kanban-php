<?php

declare(strict_types=1);

namespace App\Http;

final class Response
{
    private function __construct(
        public readonly int $status,
        public readonly string $body,
        public readonly array $headers,
    ) {}

    public static function json(array $data, int $status = 200, array $headers = []): self
    {
        return new self(
            $status,
            json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ['Content-Type' => 'application/json; charset=utf-8'] + $headers,
        );
    }

    public static function empty(): self
    {
        return new self(204, '', []);
    }

    public function send(bool $head = false): void
    {
        http_response_code($this->status);
        header('Cache-Control: no-store');
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        if (!$head) {
            echo $this->body;
        }
    }
}
