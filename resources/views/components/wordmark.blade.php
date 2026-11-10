@props(['sub' => false])
{{-- HOP ID uses the official HEL.ink jumping rabbit from the family registry. --}}
<span {{ $attributes->merge(['class' => 'inline-flex h-9 shrink-0 items-center gap-2.5']) }}><img src="{{ collect(\Hop\Ui\Assets::family('hub'))->firstWhere('slug', 'hub')['icons']['mark']['url'] }}" alt="" width="36" height="36" class="h-full w-auto"><span class="wordmark-text"><span class="hop-name hop-name-hub text-lg whitespace-nowrap text-ink">HOP ID</span>@if ($sub)<span class="wordmark-sub">Hop Easy Link</span>@endif</span></span>
