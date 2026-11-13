@php
    $cron = app(\TornadoEngine\Cron\Cron::class);
    $jobs = $cron->jobs();
    $heartbeat = \App\Models\Setting::get('cron.heartbeat');
    $running = $heartbeat && \Illuminate\Support\Carbon::parse($heartbeat)->gt(now()->subMinutes(3));
    $status = session('cron_status');
@endphp

<x-settings-card :title="__('Scheduled jobs')">
    @unless ($running)
        <p class="rounded-md border border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-950/40 px-4 py-3 text-amber-900 dark:text-amber-200">
            {!! __('The scheduler isn\'t running, so jobs only run when you click Run now. Run :cmd on the server to start it.', ['cmd' => '<code class="font-mono">docker compose up -d</code>']) !!}
        </p>
    @endunless

    @forelse ($jobs as $job)
        @php
            $settings = $cron->settings($job);
            $schedule = old('schedule', $cron->scheduleFor($job));
            $preset = array_key_exists($schedule, \TornadoEngine\Cron\Cron::PRESETS);
        @endphp
        <div class="border-t border-gray-200 dark:border-gray-700 pt-4 first:border-t-0 first:pt-0 space-y-3">
            <div>
                <p class="font-medium text-gray-900 dark:text-gray-100">{{ $job->label }}</p>
                @if ($job->description)
                    <p class="text-gray-600 dark:text-gray-400">{{ $job->description }}</p>
                @endif
                <p class="mt-1 text-gray-600 dark:text-gray-400">
                    @if ($settings->last_run_at)
                        {{ __('Last run :time:', ['time' => $settings->last_run_at->timezone(config('app.timezone'))->diffForHumans()]) }}
                        <span @class(['font-medium', 'text-green-700 dark:text-green-400' => $settings->last_status === 'ok', 'text-red-700 dark:text-red-400' => $settings->last_status !== 'ok'])>
                            {{ $settings->last_status === 'ok' ? __('worked') : __('failed') }}</span>@if ($settings->last_message) — {{ $settings->last_message }}@endif
                    @else
                        {{ __('Hasn\'t run yet.') }}
                    @endif
                </p>
                @if ($status && $status[0] === $job->key)
                    <p class="mt-1 text-green-700 dark:text-green-400">{{ $status[1] }}</p>
                @endif
            </div>

            <form method="POST" action="{{ route('cron.update', $job->key) }}" class="space-y-3"
                  x-data="{ custom: @js(! $preset), schedule: @js($schedule) }">
                @csrf
                <input type="hidden" name="schedule" :value="schedule">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <x-input-label :for="'cron-'.$job->key" :value="__('When it runs')" />
                        <select id="cron-{{ $job->key }}" class="mt-1 block w-full min-h-11 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-base focus:border-indigo-500 focus:ring-indigo-500"
                                x-on:change="custom = $event.target.value === 'custom'; if (! custom) schedule = $event.target.value">
                            @foreach (\TornadoEngine\Cron\Cron::PRESETS as $expression => $label)
                                <option value="{{ $expression }}" @selected($preset && $schedule === $expression)>{{ __($label) }}</option>
                            @endforeach
                            <option value="custom" @selected(! $preset)>{{ __('Custom (cron expression)') }}</option>
                        </select>
                    </div>
                    <div x-show="custom" x-cloak>
                        <x-input-label :for="'cron-expr-'.$job->key" :value="__('Cron expression')" />
                        <x-text-input :id="'cron-expr-'.$job->key" class="mt-1 block w-full font-mono" type="text" x-model="schedule" placeholder="0 2 * * *" />
                    </div>
                </div>
                <x-input-error :messages="$status ? [] : $errors->get('schedule')" />
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <label class="inline-flex min-h-11 items-center gap-2">
                        <input type="hidden" name="enabled" value="0">
                        <input type="checkbox" name="enabled" value="1" class="rounded text-indigo-600 border-gray-300 dark:border-gray-700 dark:bg-gray-900 focus:ring-indigo-500" @checked($settings->enabled)>
                        <span>{{ __('On') }}</span>
                    </label>
                    <div class="flex gap-3">
                        <x-secondary-button type="submit" form="cron-run-{{ $job->key }}">{{ __('Run now') }}</x-secondary-button>
                        <x-primary-button>{{ __('Save') }}</x-primary-button>
                    </div>
                </div>
            </form>
            <form id="cron-run-{{ $job->key }}" method="POST" action="{{ route('cron.run', $job->key) }}">@csrf</form>
        </div>
    @empty
        <p>{{ __('No modules have scheduled jobs yet.') }}</p>
    @endforelse
</x-settings-card>
