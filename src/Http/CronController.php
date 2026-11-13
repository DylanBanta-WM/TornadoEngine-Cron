<?php

namespace TornadoEngine\Cron\Http;

use Cron\CronExpression;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use TornadoEngine\Cron\Cron;

class CronController extends Controller
{
    public function update(Request $request, Cron $cron, string $job): RedirectResponse
    {
        $job = $cron->find($job) ?? abort(404);

        $data = $request->validate([
            'schedule' => ['required', 'string', 'max:100', function ($attribute, $value, $fail) {
                if (! CronExpression::isValidExpression($value)) {
                    $fail(__('That isn\'t a valid cron expression.'));
                }
            }],
            'enabled' => ['boolean'],
        ], [], ['schedule' => __('schedule')]);

        $settings = $cron->settings($job);
        $settings->fill([
            'schedule' => $data['schedule'] === $job->schedule ? null : $data['schedule'],
            'enabled' => $request->boolean('enabled'),
        ])->save();

        return back()->with('cron_status', [$job->key, __('Saved.')]);
    }

    public function run(Cron $cron, string $job): RedirectResponse
    {
        $job = $cron->find($job) ?? abort(404);

        $cron->run($job);

        return back()->with('cron_status', [$job->key, __('Ran it. See the result below.')]);
    }
}
