<details class="hop-switcher" data-hop-switcher>
    <summary aria-label="{{ __('Language') }}" title="{{ __('Language') }}"><span class="text-xs font-semibold">{{ strtoupper(app()->getLocale()) }}</span></summary>
    <nav class="hop-switcher-panel" aria-label="{{ __('Language') }}">
        @foreach (['en'=>'English','id'=>'Bahasa Indonesia','zh'=>'中文','es'=>'Español','ar'=>'العربية','pt'=>'Português','fr'=>'Français','ja'=>'日本語'] as $code=>$label)
            <a href="{{ request()->fullUrlWithQuery(['lang'=>$code]) }}" lang="{{ $code }}" hreflang="{{ $code }}" @if(app()->getLocale()===$code) aria-current="true" @endif>{{ $label }}</a>
        @endforeach
    </nav>
</details>
