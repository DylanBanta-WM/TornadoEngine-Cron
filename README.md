# Tornado Engine Cron

A [Tornado Engine](https://github.com/DylanBanta-WM/TornadoEngineCore) core-module: scheduled jobs. It runs Laravel's scheduler in its own container. Admins see every job on the Administration page, where they choose when it runs, turn it off, or run it now.

## Adding a job from a module

```php
use TornadoEngine\Cron\Cron;

$this->callAfterResolving(Cron::class, function (Cron $cron) {
    $cron->job('notes.cleanup', 'Clean up old notes', function () {
        $count = Note::prune();

        return "Removed {$count} notes"; // shown as the last result
    }, schedule: '0 3 * * *', description: 'Deletes notes older than a year.');
});
```

The schedule is a cron expression and is only the default. Times use the `APP_TIMEZONE` in `.env`.
