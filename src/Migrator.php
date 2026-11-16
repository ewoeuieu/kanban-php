<?php

declare(strict_types=1);

namespace App;

use Illuminate\Database\Connection;
use InvalidArgumentException;

final class Migrator
{
    public function __construct(
        private readonly Connection $database,
        private readonly string $directory,
    ) {}

    public function run(string $command): array
    {
        if (!in_array($command, ['migrate', 'rollback', 'status'], true)) {
            throw new InvalidArgumentException('Неизвестная команда мигратора.');
        }

        return $this->database->transaction(function () use ($command): array {
            $this->database->selectOne('select pg_advisory_xact_lock(138042, 1)');
            $schema = $this->database->getSchemaBuilder();
            if (!$schema->hasTable('migrations')) {
                $schema->create('migrations', function ($table): void {
                    $table->string('name')->primary();
                    $table->integer('batch');
                });
            }

            $history = $this->database->table('migrations');
            $files = glob($this->directory . '/*.php') ?: [];
            $pending = [];
            $applied = $history->pluck('batch', 'name');
            foreach ($files as $file) {
                $name = basename($file, '.php');
                if (!$applied->has($name)) {
                    $pending[$name] = $file;
                }
            }

            if ($command === 'status') {
                return [
                    'applied' => $applied->sortKeys()->keys()->all(),
                    'pending' => array_keys($pending),
                ];
            }

            $latest = (int) $applied->max();
            if ($command === 'rollback') {
                $rolledBack = [];
                $batch = $history->where('batch', $latest)->orderByDesc('name')->pluck('name');
                foreach ($batch as $name) {
                    $migration = require $this->directory . '/' . $name . '.php';
                    $migration->down($schema);
                    $this->database->table('migrations')->where('name', $name)->delete();
                    $rolledBack[] = $name;
                }

                return ['rolled_back' => $rolledBack];
            }

            foreach ($pending as $name => $file) {
                $migration = require $file;
                $migration->up($schema);
                $history->insert(['name' => $name, 'batch' => $latest + 1]);
            }

            return ['applied' => array_keys($pending)];
        });
    }
}
