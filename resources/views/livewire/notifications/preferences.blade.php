<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold">{{ __('panel.notifications.title') }}</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('panel.notifications.subtitle') }}</p>
    </div>

    @if (session('notifications_status'))
        <p class="rounded-lg bg-green-50 px-3 py-2 text-sm text-green-800 dark:bg-green-950 dark:text-green-200">{{ session('notifications_status') }}</p>
    @endif

    <form wire:submit="save" class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
        <div class="divide-y divide-gray-100 dark:divide-gray-800">
            @foreach ($catalog as $key => $meta)
                <div class="flex items-center justify-between gap-4 py-4 first:pt-0 last:pb-0">
                    <div>
                        <p class="text-sm font-medium">{{ __('panel.notifications.pref_'.$key) }}</p>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ __('panel.notifications.pref_'.$key.'_hint') }}</p>
                    </div>
                    @if ($meta['locked'] ?? false)
                        <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                            {{ __('panel.notifications.locked') }}
                        </span>
                    @else
                        <label class="relative inline-flex cursor-pointer items-center">
                            <input type="checkbox" wire:model="preferences.{{ $key }}" class="peer sr-only">
                            <span class="h-6 w-11 rounded-full bg-gray-300 after:absolute after:start-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-brand-foreground after:transition-all peer-checked:bg-brand peer-checked:after:translate-x-full dark:bg-gray-700"></span>
                        </label>
                    @endif
                </div>
            @endforeach
        </div>

        <button type="submit" class="mt-5 rounded-lg bg-brand px-4 py-2 text-sm font-medium text-brand-foreground">{{ __('panel.common.save') }}</button>
    </form>
</div>
