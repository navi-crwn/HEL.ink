{{-- "a HOP app": the small family badge for footers and sign-in pages. --}}
@php $hub = rtrim((string) config('hop.hub_url', config('hop-auth.hub_url', 'https://hub.hel.ink')), '/'); @endphp
<a href="{{ $hub }}" {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-full border border-slate-200/80 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wider text-slate-500 hover:text-slate-900 dark:border-white/10 dark:text-white/60 dark:hover:text-white']) }}>
    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="{{ \Hop\Ui\Apps::icon('hop') }}"/></svg>
    {{ $slot->isEmpty() ? 'a HOP app' : $slot }}
</a>
