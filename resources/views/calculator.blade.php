@php
    $start = '0 2 * * *';
    $fields = [
        [__('minute'), '0-59'],
        [__('hour'), '0-23'],
        [__('day (month)'), '1-31'],
        [__('month'), '1-12 or JAN-DEC'],
        [__('day (week)'), '0-6 or SUN-SAT'],
    ];
@endphp

<x-settings-card :title="__('Cron calculator')">
    <div x-data="{
            expression: @js($start),
            result: { valid: true, description: @js(\TornadoEngine\Cron\Explain::describe($start)), next: [] },
            async explain() {
                const response = await fetch(@js(route('cron.explain')) + '?expression=' + encodeURIComponent(this.expression), { headers: { Accept: 'application/json' } });
                if (response.ok) this.result = await response.json();
            },
         }" x-init="explain(); $watch('expression', () => explain())" class="space-y-4">
        <p class="text-gray-600 dark:text-gray-400">{{ __('Type a schedule to see when it runs, then copy it into a job below.') }}</p>

        <div>
            <label for="cron-calculator" class="sr-only">{{ __('Cron expression') }}</label>
            <input id="cron-calculator" type="text" x-model.debounce.250ms="expression" autocapitalize="off" autocomplete="off" spellcheck="false"
                   class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 py-3 text-center font-mono text-2xl tracking-wider focus:border-indigo-500 focus:ring-indigo-500">
            <div class="mt-2 grid grid-cols-5 gap-1 text-center text-xs text-gray-500 dark:text-gray-400">
                @foreach ($fields as [$name, $range])
                    <span>{{ $name }}</span>
                @endforeach
            </div>
        </div>

        <div aria-live="polite">
            <p x-show="result.valid" class="text-center text-lg font-medium text-gray-900 dark:text-gray-100" x-text="'“' + result.description + '”'"></p>
            <p x-show="! result.valid" x-cloak class="text-center font-medium text-red-700 dark:text-red-400">{{ __('That isn\'t a valid cron expression.') }}</p>
            <div x-show="result.valid && result.next.length" class="mt-2 text-center text-gray-600 dark:text-gray-400">
                <span>{{ __('Next runs:') }}</span>
                <ul>
                    <template x-for="time in result.next"><li x-text="time"></li></template>
                </ul>
            </div>
        </div>

        <div>
            <p class="font-medium text-gray-900 dark:text-gray-100">{{ __('Examples') }}</p>
            <div class="mt-1 flex flex-wrap gap-2">
                @foreach (\TornadoEngine\Cron\Cron::EXAMPLES as $example => $label)
                    <button type="button" x-on:click="expression = @js($example)" class="min-h-11 rounded-md border border-gray-300 dark:border-gray-600 px-3 text-sm hover:bg-gray-50 dark:hover:bg-gray-700">
                        <span class="font-mono">{{ $example }}</span> <span class="text-gray-500 dark:text-gray-400">{{ __($label) }}</span>
                    </button>
                @endforeach
            </div>
        </div>

        <table class="w-full text-sm">
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                <tr><td class="py-1.5 pe-3 font-mono">*</td><td>{{ __('any value') }}</td></tr>
                <tr><td class="py-1.5 pe-3 font-mono">,</td><td>{{ __('list of values, for example 1,15') }}</td></tr>
                <tr><td class="py-1.5 pe-3 font-mono">-</td><td>{{ __('range of values, for example 1-5') }}</td></tr>
                <tr><td class="py-1.5 pe-3 font-mono">/</td><td>{{ __('step values, for example */15') }}</td></tr>
                @foreach ($fields as [$name, $range])
                    <tr><td class="py-1.5 pe-3 font-mono whitespace-nowrap">{{ $range }}</td><td>{{ __('allowed :field values', ['field' => $name]) }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-settings-card>
