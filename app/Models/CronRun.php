<?php

declare(strict_types=1);

final class CronRun extends BaseModel
{
    protected string $table = 'cron_runs';
    protected string $columns = 'id, job_name, status, started_at, finished_at, notes';

    /** @return array<string, mixed>|null */
    public function lastInvariantResult(): ?array
    {
        return $this->selectOne(
            'SELECT id, status, started_at, finished_at, notes
               FROM cron_runs
              WHERE job_name = \'check_invariant\'
              ORDER BY started_at DESC
              LIMIT 1'
        );
    }

    /** @return list<array<string, mixed>> */
    public function recentJobs(int $limit = 10): array
    {
        return $this->select(
            'SELECT id, job_name, status, started_at, finished_at, notes
               FROM cron_runs
              ORDER BY started_at DESC
              LIMIT ' . $limit
        );
    }

    /** @return list<array<string, mixed>> */
    public function allJobs(): array
    {
        return $this->select(
            'SELECT id, job_name, status, started_at, finished_at, notes,
                    TIMESTAMPDIFF(SECOND, started_at, finished_at) AS duration_seconds
               FROM cron_runs
              ORDER BY started_at DESC'
        );
    }
}
