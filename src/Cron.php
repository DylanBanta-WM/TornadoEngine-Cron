<?php

namespace TornadoEngine\Cron;

use Closure;
use Throwable;
use TornadoEngine\Cron\Models\CronJob;

/**
 * Scheduled jobs. Modules register theirs in their service provider:
 *
 *     $this->callAfterResolving(Cron::class, function (Cron $cron) {
 *         $cron->job('notes.cleanup', 'Clean up old notes', fn () => Note::prune(), schedule: '0 3 * * *');
 *     });
 *
 * The schedule is a cron expression and only the default: admins can change it, turn the job
 * off, or run it now from Administration.
 */
class Cron
{
    public const PRESETS = [
        '*/15 * * * *' => 'Every 15 minutes',
        '0 * * * *' => 'Every hour',
        '0 2 * * *' => 'Every night at 2:00',
        '0 2 * * 0' => 'Every Sunday at 2:00',
    ];

    /** @var array<string, Job> */
    private array $jobs = [];

    public function job(string $key, string $label, Closure $handler, string $schedule = '0 2 * * *', ?string $description = null): static
    {
        $this->jobs[$key] = new Job($key, $label, $handler, $schedule, $description);

        return $this;
    }

    /** @return list<Job> */
    public function jobs(): array
    {
        $jobs = array_values($this->jobs);
        usort($jobs, fn (Job $a, Job $b) => $a->label <=> $b->label);

        return $jobs;
    }

    public function find(string $key): ?Job
    {
        return $this->jobs[$key] ?? null;
    }

    public function settings(Job $job): CronJob
    {
        return CronJob::find($job->key) ?? new CronJob(['key' => $job->key, 'enabled' => true]);
    }

    /**
     * The schedule in use: the admin's, or the job's default.
     */
    public function scheduleFor(Job $job): string
    {
        return $this->settings($job)->schedule ?? $job->schedule;
    }

    /**
     * Runs a job now and records how it went.
     */
    public function run(Job $job): bool
    {
        $settings = $this->settings($job);

        try {
            $message = ($job->handler)();
            $settings->fill(['last_status' => 'ok', 'last_message' => is_string($message) ? mb_substr($message, 0, 1000) : null]);
        } catch (Throwable $e) {
            report($e);
            $settings->fill(['last_status' => 'failed', 'last_message' => mb_substr($e->getMessage(), 0, 1000)]);
        }

        $settings->last_run_at = now();
        $settings->save();

        return $settings->last_status === 'ok';
    }
}
