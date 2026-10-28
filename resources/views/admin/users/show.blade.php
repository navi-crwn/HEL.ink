<x-app-layout>
    <x-slot name="pageTitle">HOP - {{ $user->email }}</x-slot>
    <x-page-header :title="$user->name" :subtitle="$user->email.($user->username ? ' · @'.$user->username : '')" />
    <x-flash />
    @if ($errors->any())<div class="glass rounded-2xl border-rose-400/40 px-4 py-3 text-sm text-rose-600 dark:text-rose-300">{{ $errors->first() }}</div>@endif
    <div class="grid gap-4 lg:grid-cols-3">
        <div class="glass rounded-2xl p-5 space-y-3">
            <h3 class="section-title">Account</h3>
            <dl class="text-sm">
                <div class="flex justify-between py-1"><dt class="text-muted">Status</dt><dd>@if ($user->isSuspended())<span class="chip-red">suspended</span>@else<span class="chip-green">active</span>@endif</dd></div>
                <div class="flex justify-between py-1"><dt class="text-muted">Roles</dt><dd class="text-ink">{{ implode(', ', $user->roleNames()) ?: '—' }}</dd></div>
                <div class="flex justify-between py-1"><dt class="text-muted">Verified</dt><dd class="text-ink">{{ $user->hasVerifiedEmail() ? 'yes' : 'no' }}</dd></div>
                <div class="flex justify-between py-1"><dt class="text-muted">Google</dt><dd class="text-ink">{{ $user->google_id ? 'linked' : '—' }}</dd></div>
                <div class="flex justify-between py-1"><dt class="text-muted">2FA</dt><dd class="text-ink">{{ $user->hasTwoFactor() ? 'on' : 'off' }}</dd></div>
                <div class="flex justify-between py-1"><dt class="text-muted">Joined</dt><dd class="text-ink">{{ $user->created_at->format('j M Y') }}</dd></div>
                <div class="flex justify-between py-1"><dt class="text-muted">API tokens</dt><dd class="text-ink">{{ $tokens->count() }}</dd></div>
            </dl>
            <div class="flex flex-wrap gap-2 pt-2">
                @if ($user->isSuspended())
                    <form method="POST" action="{{ route('admin.users.activate', $user) }}">@csrf<button class="btn-primary btn-sm">Reinstate</button></form>
                @else
                    <form method="POST" action="{{ route('admin.users.suspend', $user) }}" data-confirm="Suspend this account in every HOP app?">@csrf<button class="btn-soft btn-sm">Suspend</button></form>
                @endif
                @if (auth()->user()->isOwner())
                    <form method="POST" action="{{ route('admin.users.role', $user) }}">@csrf<input type="hidden" name="role" value="admin"><input type="hidden" name="grant" value="{{ $user->hasRole('admin') ? 0 : 1 }}"><button class="btn-ghost btn-sm">{{ $user->hasRole('admin') ? 'Remove admin' : 'Make admin' }}</button></form>
                @endif
                <form method="POST" action="{{ route('admin.users.destroy', $user) }}" data-confirm="Delete this account and everything it owns in every app? This cannot be undone.">@csrf @method('DELETE')<button class="btn-ghost btn-sm text-rose-600 dark:text-rose-400">Delete</button></form>
            </div>
        </div>
        <div class="glass rounded-2xl p-5 lg:col-span-2 space-y-4">
            <div>
                <h3 class="section-title">Recent sign-ins</h3>
                <ul class="mt-2 divide-glass text-sm">
                    @forelse ($logins as $l)
                        <li class="flex flex-wrap gap-2 py-1.5"><span class="text-muted">{{ $l->logged_in_at->format('j M Y H:i') }}</span><span class="text-ink">{{ $l->provider }}</span><span class="text-muted">{{ $l->ip_address }} {!! country_flag($l->country) !!} {{ $l->city }}</span><span class="text-muted">{{ $l->device }} · {{ $l->browser }}</span>@if ($l->is_proxy)<span class="chip-red">proxy</span>@endif</li>
                    @empty
                        <li class="py-1.5 text-muted">No sign-ins recorded.</li>
                    @endforelse
                </ul>
            </div>
            <div>
                <h3 class="section-title">Activity</h3>
                <ul class="mt-2 divide-glass text-sm">
                    @forelse ($activity as $a)
                        <li class="py-1.5"><span class="text-muted">{{ $a->created_at->format('j M Y H:i') }}</span> <span class="text-ink">{{ $a->description }}</span></li>
                    @empty
                        <li class="py-1.5 text-muted">No activity recorded.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</x-app-layout>
