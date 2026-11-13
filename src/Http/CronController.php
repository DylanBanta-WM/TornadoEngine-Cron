<?php

namespace TornadoEngine\Cron\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use TornadoEngine\Cron\Cron;
use TornadoEngine\Cron\Explain;

class CronController extends Controller
{
    public function update(Request $request, Cron $cron, string $job): RedirectResponse
    {
        $job = $cron->find($job) ?? abort(404);

        $data = $request->validate([
            'schedule' => ['required', 'string', 'max:100', function ($attribute, $value, $fail) {
                if (! Explain::valid($value)) {
                    $fail(__('That isn\'t a valid cron expression.'));
                }
            }],
            'enabled' => ['boolean'],
        ], [], ['schedule' => __('schedule')]);

        $settings = $cron->settings($job);
        $settings->fill([
            'schedule' => trim($data['schedule']) === $job->schedule ? null : trim($data['schedule']),
            'enabled' => $request->boolean('enabled'),
        ])->save();

        return back()->with('cron_status', [$job->key, __('Saved.')]);
    }

    /**
     * For the calculator: the expression in plain English and its next runs.
     */
    public function explain(Request $request): JsonResponse
    {
        $expression = (string) $request->query('expression', '');

        return response()->json([
            'valid' => Explain::valid($expression),
            'description' => Explain::describe($expression),
            'next' => array_map(fn ($time) => $time->format('D j M Y, g:i a'), Explain::next($expression)),
        ]);
    }

    public function run(Cron $cron, string $job): RedirectResponse
    {
        $job = $cron->find($job) ?? abort(404);

        if ($cron->run($job, wait: false) === null) {
            return back()->with('cron_status', [$job->key, __('Another job is running. Jobs run one at a time, so try again when it finishes.'), false]);
        }

        return back()->with('cron_status', [$job->key, __('Ran it. See the result above.')]);
    }
}
