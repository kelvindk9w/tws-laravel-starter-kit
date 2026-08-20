<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold">{{ __('panel.dashboard.greeting', ['name' => $user->name]) }}</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            {{ __('panel.dashboard.user_code') }}: <code class="rounded bg-gray-100 px-1.5 py-0.5 dark:bg-gray-800">{{ $user->codigo_publico }}</code>
        </p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <a href="{{ route('panel.api-keys') }}" class="rounded-xl border border-gray-200 bg-white p-5 hover:border-(--brand) dark:border-gray-800 dark:bg-gray-900">
            <p class="text-3xl font-semibold">{{ $activeKeysCount }}</p>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('panel.dashboard.summary_keys') }}</p>
        </a>
        <a href="{{ route('panel.projects') }}" class="rounded-xl border border-gray-200 bg-white p-5 hover:border-(--brand) dark:border-gray-800 dark:bg-gray-900">
            <p class="text-3xl font-semibold">{{ $projectsCount }}</p>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('panel.dashboard.summary_projects') }}</p>
        </a>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('panel.dashboard.quick_actions') }}</h2>
        <div class="mt-3 flex flex-wrap gap-2">
            <a href="{{ route('panel.api-keys') }}" class="rounded-lg bg-(--brand) px-4 py-2 text-sm font-medium text-white">{{ __('panel.dashboard.new_api_key') }}</a>
            <a href="{{ route('panel.projects') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium dark:border-gray-700">{{ __('panel.dashboard.new_project') }}</a>
            <a href="{{ route('panel.profile') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium dark:border-gray-700">{{ __('panel.dashboard.manage_profile') }}</a>
        </div>
    </div>
</div>
