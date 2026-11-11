<x-guest-layout>
    <section class="mx-auto max-w-3xl px-4 py-6">
        <div class="text-center">
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Welcome to {{ config('app.name') }}</h1>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Get started with your link shortening journey</p>
        </div>
        @if(session('success'))
        <div class="mt-6 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 p-4">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="text-sm font-medium text-green-800 dark:text-green-200">
                    {{ session('success') }}
                </p>
            </div>
        </div>
        @endif
        <div class="mt-8">
            <h2 class="text-lg font-bold text-slate-900 dark:text-white mb-4">Quick Start Guide</h2>
            <div class="grid gap-3">
                <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-xs dark:border-slate-700 dark:bg-slate-800">
                    <div class="flex items-center gap-2">
                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-100 text-lg dark:bg-blue-900/30">
                            🔗
                        </div>
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Create short links</h3>
                    </div>
                    <p class="mt-2 text-xs text-slate-600 dark:text-slate-400">
                        Shorten URLs with custom slugs, passwords, and tags.
                    </p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-xs dark:border-slate-700 dark:bg-slate-800">
                    <div class="flex items-center gap-2">
                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-purple-100 text-lg dark:bg-purple-900/30">
                            📁
                        </div>
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Organize with folders</h3>
                    </div>
                    <p class="mt-2 text-xs text-slate-600 dark:text-slate-400">
                        Group related links for easy management.
                    </p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-xs dark:border-slate-700 dark:bg-slate-800">
                    <div class="flex items-center gap-2">
                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-green-100 text-lg dark:bg-green-900/30">
                            📊
                        </div>
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Track analytics</h3>
                    </div>
                    <p class="mt-2 text-xs text-slate-600 dark:text-slate-400">
                        See clicks, locations, and device breakdowns.
                    </p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-xs dark:border-slate-700 dark:bg-slate-800">
                    <div class="flex items-center gap-2">
                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-orange-100 text-lg dark:bg-orange-900/30">
                            ⚙️
                        </div>
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Manage settings</h3>
                    </div>
                    <p class="mt-2 text-xs text-slate-600 dark:text-slate-400">
                        Configure branding and manage your account.
                    </p>
                </div>
            </div>
        </div>
        <div class="mt-8 flex flex-col items-center justify-center gap-2 sm:flex-row">
            @if($ssoApp = \App\Http\Controllers\SsoController::cameFrom(request()))
                <a href="{{ \App\Http\Controllers\SsoController::continueUrl($ssoApp) }}" class="btn-primary w-full sm:w-auto">
                    Continue to {{ $ssoApp->name }}
                </a>
                <a href="{{ route('dashboard') }}" class="btn-outline w-full sm:w-auto">
                    Go to {{ config('app.name') }} dashboard
                </a>
            @else
                <a href="{{ route('dashboard') }}" class="btn-primary w-full sm:w-auto">
                    Go to Dashboard
                </a>
                <form method="POST" action="{{ route('onboarding.skip') }}" class="w-full sm:w-auto">
                    @csrf
                    <button type="submit" class="btn-outline w-full sm:w-auto">
                        Skip
                    </button>
                </form>
            @endif
        </div>
    </section>
</x-guest-layout>
