<?php

declare(strict_types=1);

namespace App;

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;

final class SessionCleanup
{
    public function __construct(
        private readonly Connection $database,
        private readonly DatabaseSessionHandler $sessions,
    ) {}

    public function run(): int
    {
        return $this->database->transaction(function (): int {
            // Запуски из cron и консоли используют одну запись и выполняются по очереди.
            $job = $this->job()->lockForUpdate()->firstOrFail();
            $deleted = $this->sessions->gc(0);
            $this->job()->update([
                'last_success_at' => time(),
                'deleted_total' => $job->deleted_total + $deleted,
            ]);

            return $deleted;
        });
    }

    public function metrics(): string
    {
        $job = $this->job()->firstOrFail();
        $age = max(0, time() - ($job->last_success_at ?? $job->initialized_at));
        $lastSuccess = $job->last_success_at ?? 0;
        $expired = $this->sessions->countExpired();

        return <<<METRICS
            # HELP kanban_session_cleanup_age_seconds Seconds since the last successful cleanup or its initialization.
            # TYPE kanban_session_cleanup_age_seconds gauge
            kanban_session_cleanup_age_seconds {$age}
            # HELP kanban_session_cleanup_last_success_timestamp_seconds Unix timestamp of last successful cleanup, zero before the first run.
            # TYPE kanban_session_cleanup_last_success_timestamp_seconds gauge
            kanban_session_cleanup_last_success_timestamp_seconds {$lastSuccess}
            # HELP kanban_expired_sessions Expired sessions currently stored in PostgreSQL.
            # TYPE kanban_expired_sessions gauge
            kanban_expired_sessions {$expired}
            # HELP kanban_session_cleanup_deleted_total Expired sessions removed by the cleanup job.
            # TYPE kanban_session_cleanup_deleted_total counter
            kanban_session_cleanup_deleted_total {$job->deleted_total}

            METRICS;
    }

    private function job(): Builder
    {
        return $this->database->table('scheduled_jobs')->where('name', 'sessions:prune');
    }
}
