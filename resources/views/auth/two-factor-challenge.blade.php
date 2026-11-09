<x-guest-layout>
    <div class="mb-6">
        <h1 class="page-title">{{ __('Two-factor code') }}</h1>
        <p class="mt-1 text-sm text-muted">{{ __('Please confirm access to your account by entering the authentication code provided by your authenticator application.') }}</p>
    </div>
    <form method="POST" action="{{ route('two-factor.verify') }}">
        @csrf
        {{-- A code is short: the field is too (the recovery codes are ten characters). --}}
        <div class="field-short">
            <x-input-label for="code" :value="__('Code')" />
            <x-text-input id="code" type="text" name="code" required autofocus autocomplete="one-time-code" inputmode="numeric" />
            <x-input-error :messages="$errors->get('code')" class="mt-2" />
        </div>
        <div class="mt-4 flex flex-wrap items-center justify-between gap-2">
            <button type="submit" form="two-factor-logout" class="btn-link -ml-1.5">
                {{ __('Use a different account') }}
            </button>
            <x-primary-button>
                {{ __('Verify') }}
            </x-primary-button>
        </div>
        <div class="mt-4 p-3 bg-slate-100 dark:bg-slate-800 rounded-lg">
            <p class="text-xs text-slate-600 dark:text-slate-400">
                <strong>{{ __('Lost your device?') }}</strong> {{ __('Enter one of your recovery codes instead of the app code.') }}
            </p>
        </div>
    </form>
    <form id="two-factor-logout" method="POST" action="{{ route('logout') }}" class="hidden">
        @csrf
    </form>
</x-guest-layout>
