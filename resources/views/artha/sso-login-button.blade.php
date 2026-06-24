@php
    $arthaSsoEnabled = (bool) config('artha.sso.enabled', false);
    $arthaCrmLaunchUrl = (string) config('artha.sso.crm_launch_url', '');
@endphp

@if ($arthaSsoEnabled && filled($arthaCrmLaunchUrl))
    <div class="fi-artha-sso mt-6">
        <div class="flex items-center gap-3" aria-hidden="true">
            <span class="h-px flex-1 bg-gray-200 dark:bg-white/10"></span>
            <span class="text-xs font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">or</span>
            <span class="h-px flex-1 bg-gray-200 dark:bg-white/10"></span>
        </div>

        <a
            href="{{ $arthaCrmLaunchUrl }}"
            class="mt-6 flex w-full items-center justify-center gap-2 rounded-lg bg-[#1B1F3B] px-4 py-2.5 text-sm font-semibold text-white shadow-sm ring-1 ring-inset ring-[#1B1F3B] transition hover:bg-[#252a52] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#C8A24B]"
        >
            <svg class="h-4 w-4 text-[#C8A24B]" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <circle cx="5" cy="5" r="1.8"/><circle cx="12" cy="5" r="1.8"/><circle cx="19" cy="5" r="1.8"/>
                <circle cx="5" cy="12" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="19" cy="12" r="1.8"/>
                <circle cx="5" cy="19" r="1.8"/><circle cx="12" cy="19" r="1.8"/><circle cx="19" cy="19" r="1.8"/>
            </svg>
            Continue with Artha
        </a>
    </div>
@endif
