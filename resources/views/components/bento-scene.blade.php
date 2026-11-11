{{-- A small decorative scene at the top of a bento box (resources/css/bento-scenes.css). Runs only while the box is on screen. --}}
@props(['slug' => null, 'kind' => null, 'color' => null])
@php
    $kind ??= ['linkhop' => 'link', 'biohop' => 'bio', 'pixelhop' => 'pixel', 'notehop' => 'note', 'filehop' => 'file', 'payhop' => 'pay', 'hub' => 'id'][$slug] ?? 'bars';
@endphp
<span class="sc sc-{{ $kind }}" data-scene aria-hidden="true" @if ($color) style="--sc: {{ $color }}" @endif>
    @switch($kind)
        @case('link')<i class="a"></i><i class="b"></i>@break
        @case('file')<b>PNG</b><b>WEBP</b>@break
        @case('bars')<i></i><i></i><i></i><i></i>@break
        @default<i></i><i></i><i></i>
    @endswitch
</span>
