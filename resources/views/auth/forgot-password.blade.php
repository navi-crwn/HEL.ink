<x-guest-layout>
    <div class="mb-6">
        <h1 class="page-title">{{ __('Reset your HOP ID password') }}</h1>
        <p class="mt-1 text-sm text-muted">{{ __("Enter the email of your HOP ID and we'll send you a reset link. The new password works on :hub and every HOP app.", ['hub' => config('app.name')]) }}</p>
    </div>
    <x-auth-session-status class="mb-4" :status="session('status')" />
    <form method="POST" action="{{ route('password.email') }}" class="space-y-4" x-data="turnstileForm(@js(config('services.turnstile.site_key')))" x-on:submit="handleSubmit($event)">
        @csrf
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>
        <x-turnstile size="flexible" />
        <x-primary-button class="w-full py-3 text-base"><span x-show="!waiting">{{ __('Send reset link') }}</span><span x-cloak x-show="waiting">{{ __('Verifying…') }}</span></x-primary-button>
        <p class="pt-1 text-center text-sm text-muted"><a href="{{ route('login') }}" class="hover:text-ink hover:underline">{{ __('Back to login') }}</a></p>
    </form>
</x-guest-layout>
