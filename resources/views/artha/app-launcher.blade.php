@php
    $apps = config('artha.apps', []);
    $current = config('artha.current');
@endphp

<div
    x-data="{ open: false }"
    x-on:keydown.escape.window="open = false"
    class="fi-artha-launcher relative"
>
    <button
        type="button"
        x-on:click="open = !open"
        x-bind:aria-expanded="open"
        aria-label="Artha apps"
        title="Artha apps"
        class="flex items-center justify-center w-9 h-9 rounded-lg text-gray-500 hover:text-primary-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:text-primary-400 dark:hover:bg-white/5 transition"
    >
        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <circle cx="5" cy="5" r="1.8"/><circle cx="12" cy="5" r="1.8"/><circle cx="19" cy="5" r="1.8"/>
            <circle cx="5" cy="12" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="19" cy="12" r="1.8"/>
            <circle cx="5" cy="19" r="1.8"/><circle cx="12" cy="19" r="1.8"/><circle cx="19" cy="19" r="1.8"/>
        </svg>
    </button>

    <div
        x-show="open"
        x-cloak
        x-transition.origin.top.right
        x-on:click.outside="open = false"
        class="absolute right-0 z-50 mt-2 w-80 origin-top-right rounded-xl bg-white dark:bg-gray-900 shadow-2xl ring-1 ring-gray-950/5 dark:ring-white/10 overflow-hidden"
        style="display: none;"
    >
        <div class="flex items-center gap-2 px-4 py-3 border-b border-gray-100 dark:border-white/10">
            <span class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Artha Business OS</span>
        </div>

        <div class="grid grid-cols-2 gap-1 p-2">
            @foreach ($apps as $app)
                @php $isCurrent = ($app['key'] ?? null) === $current; @endphp
                <a
                    href="{{ $app['url'] }}"
                    @if (! $isCurrent) target="_blank" rel="noopener" @endif
                    @class([
                        'group flex flex-col gap-1.5 rounded-lg p-3 transition',
                        'bg-primary-50 dark:bg-primary-500/10 ring-1 ring-primary-500/30' => $isCurrent,
                        'hover:bg-gray-50 dark:hover:bg-white/5' => ! $isCurrent,
                    ])
                >
                    <span class="flex items-center justify-center w-9 h-9 rounded-lg bg-primary-600 text-white shadow-sm">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $app['icon'] }}" />
                        </svg>
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $app['name'] }}</span>
                        @if ($isCurrent)
                            <span class="text-[10px] font-medium px-1.5 py-0.5 rounded bg-primary-600 text-white">Current</span>
                        @endif
                    </span>
                    <span class="text-xs text-gray-500 dark:text-gray-400 leading-tight">{{ $app['description'] }}</span>
                </a>
            @endforeach
        </div>
    </div>
</div>
