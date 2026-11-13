<?php

namespace TornadoEngine\Cron;

use App\Administration\Administration;
use App\Models\Setting;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Throwable;

class CronServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Cron::class);
    }

    public function boot(Cron $cron, Administration $administration): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'cron');
        $this->loadRoutesFrom(__DIR__.'/../routes/cron.php');

        $administration->card('cron::administration-card', order: 40);

        // The scheduler container asks for the schedule every minute.
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) use ($cron) {
            $schedule->call(fn () => Setting::put('cron.heartbeat', now()->toIso8601String()))
                ->everyMinute()
                ->name('cron:heartbeat');

            foreach ($cron->jobs() as $job) {
                try {
                    $settings = $cron->settings($job);
                } catch (Throwable) {
                    return; // The database isn't ready yet (for example, before migrations).
                }

                if (! $settings->enabled) {
                    continue;
                }

                $schedule->call(fn () => $cron->run($job))
                    ->cron($settings->schedule ?? $job->schedule)
                    ->name('cron:'.$job->key)
                    ->withoutOverlapping();
            }
        });
    }
}
