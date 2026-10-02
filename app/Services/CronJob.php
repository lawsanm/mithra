<?php

declare(strict_types=1);

/**
 * Runs one scheduled job the way every script in /scripts must
 * (Rules/CONVENTIONS.md §8): one `cron_runs` row per run, opened before the
 * work and closed with its outcome, and a non-zero exit code on failure.
 *
 * The job itself is a service method that is safe to run twice — it only
 * touches rows still in the state it is looking for — and returns a short
 * summary for the log.
 */
final class CronJob
{
    public function __construct(private CronRun $runs)
    {
    }

    /**
     * @param callable(): string $work returns the summary written to the log
     *
     * @return int the process exit code: 0 on success, 1 on failure
     */
    public function run(string $jobName, callable $work): int
    {
        $runId = $this->runs->start($jobName);

        try {
            $summary = $work();
        } catch (Throwable $exception) {
            error_log((string) $exception);
            $this->runs->finish($runId, false, $exception->getMessage());
            fwrite(STDERR, $jobName . ' failed: ' . $exception->getMessage() . PHP_EOL);

            return 1;
        }

        $this->runs->finish($runId, true, $summary);
        fwrite(STDOUT, $jobName . ': ' . $summary . PHP_EOL);

        return 0;
    }
}
