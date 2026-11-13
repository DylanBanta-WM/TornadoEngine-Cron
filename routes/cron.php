<?php

use Illuminate\Support\Facades\Route;
use TornadoEngine\Cron\Http\CronController;

Route::middleware(['web', 'auth', 'can:administration'])
    ->prefix('administration/cron')
    ->name('cron.')
    ->group(function () {
        Route::post('/{job}', [CronController::class, 'update'])->name('update');
        Route::post('/{job}/run', [CronController::class, 'run'])->name('run');
    });
