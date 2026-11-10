<div class="h-demo" data-home-demo="{{ $slug }}" data-prefix="{{ $app['public_prefix'] }}">
    @if ($slug === 'linkhop')
        <label for="demo-{{ $slug }}">{{ __('Paste your link') }}</label><input id="demo-{{ $slug }}" type="url" value="https://example.com/my-next-project" dir="ltr"><output aria-live="polite" dir="ltr">{{ $app['public_prefix'] }}demo</output>
    @elseif ($slug === 'biohop')
        <div class="h-mini-bio" data-theme="day"><strong>HEL.ink</strong><p>{{ __('Your links, together.') }}</p><a href="#connects">{{ __('One account for every HOP app.') }}</a></div>
        <div class="h-tabs" role="group" aria-label="{{ __('Try a theme') }}">@foreach (['day' => 'Day', 'night' => 'Night', 'garden' => 'Garden'] as $theme => $label)<button type="button" data-theme-choice="{{ $theme }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}">{{ __($label) }}</button>@endforeach</div>
    @elseif ($slug === 'pixelhop')
        <div class="h-drop" data-drop><img src="{{ $app['screenshot'] }}" alt="{{ __('Sample image') }}" draggable="true" width="120" height="80" loading="lazy"><p>{{ __('Drag the sample here, or press the button.') }}</p></div><button type="button">{{ __('Compress sample') }}</button><output aria-live="polite" dir="ltr">{{ $app['public_prefix'] }}demo</output>
    @elseif ($slug === 'payhop')
        <label for="demo-{{ $slug }}">{{ __('Bill total') }}</label><input id="demo-{{ $slug }}" type="number" value="120" min="0" max="1000000" step="0.01" inputmode="decimal" dir="ltr">
        <label for="demo-{{ $slug }}-people">{{ __('People') }}</label><select id="demo-{{ $slug }}-people">@foreach ([2, 3, 4, 5, 6] as $count)<option value="{{ $count }}" @selected($count === 3)>{{ $count }}</option>@endforeach</select>
        <output aria-live="polite" aria-label="{{ __('Share per person') }}" dir="ltr">0.00</output>
    @elseif ($slug === 'filehop')
        <label for="demo-{{ $slug }}">{{ __('Paste a small table (CSV)') }}</label><textarea id="demo-{{ $slug }}" rows="3" maxlength="400" dir="ltr">name,files
Ana,3
Budi,12</textarea><output aria-live="polite" aria-label="{{ __('JSON preview') }}" dir="ltr">[]</output>
    @else
        <label for="demo-{{ $slug }}">{{ __('Write your note') }}</label><textarea id="demo-{{ $slug }}" rows="2" maxlength="200">{{ __('A little idea, just for you.') }}</textarea><output aria-live="polite" aria-label="{{ __('Encrypted preview') }}" dir="ltr">AES-GCM</output>
    @endif
    <p class="h-small">{{ __('Demo only. Everything stays in your browser.') }}</p>
</div>
