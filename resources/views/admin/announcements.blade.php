<x-app-layout>
    <x-slot name="pageTitle">HOP - Manage announcements</x-slot>
    <x-page-header title="Announcements" subtitle="Published notices show on hel.ink, the launcher and /announcements." />
    <x-flash />
    @if ($errors->any())<div class="glass rounded-2xl border-rose-400/40 px-4 py-3 text-sm text-rose-600 dark:text-rose-300">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ route('admin.announcements.store') }}" class="glass grid gap-3 rounded-2xl p-5 md:grid-cols-2">
        @csrf
        <div class="md:col-span-2"><label class="label" for="a-title">Title</label><input id="a-title" name="title" class="input" required maxlength="160" value="{{ old('title') }}"></div>
        <div class="md:col-span-2"><label class="label" for="a-body">Body</label><textarea id="a-body" name="body" rows="5" class="input" required>{{ old('body') }}</textarea></div>
        <div><label class="label" for="a-level">Level</label><select id="a-level" name="level" class="input">@foreach (\App\Models\Announcement::LEVELS as $l)<option value="{{ $l }}">{{ ucfirst($l) }}</option>@endforeach</select></div>
        <div><label class="label" for="a-app">App</label><select id="a-app" name="app_slug" class="input"><option value="">HOP (all apps)</option>@foreach ($apps as $app)<option value="{{ $app->slug }}">{{ $app->name }}</option>@endforeach</select></div>
        <div><label class="label" for="a-pub">Publish at (empty: draft)</label><input id="a-pub" type="datetime-local" name="published_at" class="input"></div>
        <div><label class="label" for="a-exp">Expires at</label><input id="a-exp" type="datetime-local" name="expires_at" class="input"></div>
        <div class="flex items-center gap-3 md:col-span-2"><label class="flex items-center gap-2 text-sm text-muted"><input type="checkbox" name="publish_now" value="1"> Publish now</label><button class="btn-primary btn-sm">Save</button></div>
    </form>
    <div class="glass divide-glass rounded-2xl">
        @forelse ($announcements as $a)
            <div class="flex flex-wrap items-center gap-3 px-5 py-3">
                <span class="chip-blue">{{ $a->app_slug ?: 'HOP' }}</span>
                <a href="{{ route('announcements.show', $a) }}" class="font-medium text-ink hover:underline">{{ $a->title }}</a>
                <span class="text-xs text-muted">{{ $a->level }} · {{ $a->isLive() ? 'live' : ($a->published_at ? ($a->published_at->isFuture() ? 'scheduled' : 'expired') : 'draft') }}{{ $a->published_at ? ' · '.$a->published_at->format('j M Y H:i') : '' }}</span>
                <div class="ml-auto flex gap-2">
                    @unless ($a->published_at)
                        <form method="POST" action="{{ route('admin.announcements.update', $a) }}">@csrf @method('PUT')<input type="hidden" name="title" value="{{ $a->title }}"><input type="hidden" name="body" value="{{ $a->body }}"><input type="hidden" name="level" value="{{ $a->level }}"><input type="hidden" name="app_slug" value="{{ $a->app_slug }}"><input type="hidden" name="publish_now" value="1"><button class="btn-soft btn-sm">Publish</button></form>
                    @endunless
                    <form method="POST" action="{{ route('admin.announcements.destroy', $a) }}" data-confirm="Delete this announcement?">@csrf @method('DELETE')<button class="btn-ghost btn-sm text-rose-600 dark:text-rose-400">Delete</button></form>
                </div>
            </div>
        @empty
            <p class="px-5 py-4 text-sm text-muted">No announcements yet.</p>
        @endforelse
    </div>
    {{ $announcements->links() }}
</x-app-layout>
