<?php

declare(strict_types=1);

namespace App;

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use SessionHandlerInterface;
use SessionUpdateTimestampHandlerInterface;

final class DatabaseSessionHandler implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface
{
    private array $locks = [];

    public function __construct(
        private readonly Connection $database,
        private readonly int $ttl,
    ) {}

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        foreach (array_keys($this->locks) as $id) {
            $this->database->selectOne('select pg_advisory_unlock(hashtextextended(?, 138043))', [$id]);
        }
        $this->locks = [];

        return true;
    }

    public function validateId(string $id): bool
    {
        if (preg_match('/\A[a-zA-Z0-9,-]{1,128}\z/', $id) !== 1) {
            return false;
        }

        $this->lock($id);

        return $this->database->table('sessions')->where('id', $id)->where('expires_at', '>', time())->exists();
    }

    public function read(string $id): string
    {
        $this->lock($id);
        $payload = $this->database->table('sessions')
            ->where('id', $id)->where('expires_at', '>', time())->value('payload');

        return $payload === null ? '' : (base64_decode($payload, true) ?: '');
    }

    public function write(string $id, string $data): bool
    {
        $this->lock($id);
        $this->database->table('sessions')->upsert(
            ['id' => $id, 'payload' => base64_encode($data), 'expires_at' => time() + $this->ttl],
            ['id'],
            ['payload', 'expires_at'],
        );

        return true;
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        return $this->write($id, $data);
    }

    public function destroy(string $id): bool
    {
        $this->lock($id);
        $this->database->table('sessions')->where('id', $id)->delete();

        return true;
    }

    public function gc(int $max_lifetime): int
    {
        return $this->expiredSessions()->delete();
    }

    public function countExpired(): int
    {
        return $this->expiredSessions()->count();
    }

    private function expiredSessions(): Builder
    {
        return $this->database->table('sessions')->where('expires_at', '<=', time());
    }

    private function lock(string $id): void
    {
        if (!isset($this->locks[$id])) {
            $this->database->selectOne('select pg_advisory_lock(hashtextextended(?, 138043))', [$id]);
            $this->locks[$id] = true;
        }
    }
}
