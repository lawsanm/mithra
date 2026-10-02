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

    /**
     * Most recently failed jobs, for the admin notification feed.
     *
     * @return list<array<string, mixed>>
     */
    public function recentFailed(int $limit = 5): array
    {
        $statement = $this->pdo->prepare(
            "SELECT job_name, started_at, notes FROM cron_runs
              WHERE status = 'failed'
              ORDER BY started_at DESC
              LIMIT :limit"
        );
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
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

    /** Open a run's log row before the job does anything (Rules/CONVENTIONS.md §8). */
    public function start(string $jobName): int
    {
        $statement = $this->pdo->prepare(
            "INSERT INTO cron_runs (job_name, status) VALUES (:job, 'running')"
        );
        $statement->execute(['job' => $jobName]);

        return (int) $this->pdo->lastInsertId();
    }

    /** Close a run's log row with its outcome. */
    public function finish(int $id, bool $succeeded, string $notes): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE cron_runs SET status = :status, finished_at = NOW(), notes = :notes WHERE id = :id'
        );
        $statement->execute([
            'status' => $succeeded ? 'success' : 'failed',
            'notes'  => mb_substr($notes, 0, 2000),
            'id'     => $id,
        ]);
    }
}
