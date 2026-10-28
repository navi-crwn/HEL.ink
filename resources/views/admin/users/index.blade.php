<x-app-layout>
    <x-slot name="pageTitle">HOP - Accounts</x-slot>
    <x-page-header title="Accounts" subtitle="Every HOP ID. Suspending here signs the account out of every app." />
    <x-flash />
    <form method="GET" class="glass flex flex-wrap items-center gap-2 rounded-2xl p-3">
        <input type="search" name="q" value="{{ $q }}" placeholder="Email, name or @username" class="input flex-1">
        <label class="flex items-center gap-2 text-sm text-muted"><input type="checkbox" name="status" value="suspended" @checked(request('status') === 'suspended')> Suspended only</label>
        <button class="btn-primary btn-sm">Search</button>
    </form>
    <div class="glass overflow-x-auto rounded-2xl">
        <table class="min-w-full text-sm">
            <thead class="text-left text-xs uppercase tracking-wider text-muted"><tr><th class="px-4 py-3">Account</th><th class="px-4 py-3">Roles</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Last sign-in</th><th class="px-4 py-3">Joined</th></tr></thead>
            <tbody class="divide-glass">
                @forelse ($users as $u)
                    <tr>
                        <td class="px-4 py-3"><a href="{{ route('admin.users.show', $u) }}" class="font-medium text-ink hover:underline">{{ $u->name }}</a><br><span class="text-xs text-muted">{{ $u->email }}{{ $u->username ? ' · @'.$u->username : '' }}</span></td>
                        <td class="px-4 py-3 text-xs text-muted">{{ implode(', ', $u->roleNames()) ?: '—' }}</td>
                        <td class="px-4 py-3">@if ($u->isSuspended())<span class="chip-red">suspended</span>@else<span class="chip-green">active</span>@endif</td>
                        <td class="px-4 py-3 text-xs text-muted">{{ $u->last_login_at?->diffForHumans() ?? 'never' }}</td>
                        <td class="px-4 py-3 text-xs text-muted">{{ $u->created_at->format('j M Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-muted">No accounts match.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $users->links() }}
</x-app-layout>
