<x-app-layout>
    <x-slot name="pageTitle">{{ __('Account') }} · HOP</x-slot>
    <x-page-header title="Account" subtitle="Profile, password and account removal.">
        <x-slot name="actions">
            <a href="{{ route('settings') }}" class="btn-outline btn-sm">All settings</a>
        </x-slot>
    </x-page-header>

    {{-- auth-card maps the Breeze partials' gray utilities onto the theme tokens. --}}
    <div class="auth-card glass mx-auto w-full max-w-4xl divide-glass rounded-2xl">
        @if ($user->isSuperAdmin())
            <p role="status" class="p-5">{{ __('Superadmin accounts are permanent and read-only, including for the account itself.') }}</p>
            <dl class="p-5"><dt>{{ __('Name') }}</dt><dd>{{ $user->name }}</dd><dt>Email</dt><dd>{{ $user->email }}</dd></dl>
        @else
        <div class="p-5 [&_section>div]:max-w-none [&_header+form]:max-w-sm">
            @include('profile.partials.update-profile-information-form')
        </div>
        <div class="p-5 [&_header+form]:max-w-sm">
            @include('profile.partials.update-password-form')
        </div>
        <div class="p-5">
            @include('profile.partials.delete-user-form')
        </div>
        @endif
    </div>
</x-app-layout>
