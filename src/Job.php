<?php

namespace TornadoEngine\Cron;

use Closure;

/**
 * A job a module registered. Its handler may return a short message to show as the result.
 */
final readonly class Job
{
    public function __construct(
        public string $key,
        public string $label,
        public Closure $handler,
        public string $schedule,
        public ?string $description,
    ) {}
}
