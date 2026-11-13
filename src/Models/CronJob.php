<?php

namespace TornadoEngine\Cron\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An admin's settings for a job, and how its last run went.
 */
class CronJob extends Model
{
    protected $table = 'cron_jobs';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'schedule', 'enabled', 'last_run_at', 'last_status', 'last_message'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'last_run_at' => 'datetime'];
    }
}
