@php
    use App\Support\Feedback;
    use Hop\Ui\Locales;

    $val = fn (string $key, mixed $default = '') => $input[$key] ?? old($key, $default);
    $chosenApp = $val('app', $app);
    $chosenKind = $val('kind');
    $typed = $val('message');
    $typed = is_string($typed) ? $typed : '';
    $kinds = ['problem' => __('Problem'), 'idea' => __('Idea or suggestion'), 'other' => __('Other')];
    $muted = 'text-slate-600 dark:text-white/70';
    $locale = Locales::names()[app()->getLocale()] ?? app()->getLocale();
    $screen = $from ? Feedback::NAMES[$app].' · '.$from : Feedback::NAMES[$app];
    $privacy = '<a href="'.e(route('privacy')).'" class="underline underline-offset-2 hover:text-slate-900 dark:hover:text-white">'.e(__('Privacy')).'</a>';
    $turnstile = $member ? null : $turnstileKey;
@endphp
<x-marketing-layout :title="__('Send feedback').' · HOP'" :description="__('Report a problem or share an idea with HOP. No e-mail address is needed.')" :themed="true" :noindex="true" :back="false">
    @vite('resources/js/feedback.js')
    <section class="mx-auto max-w-2xl px-4 py-10 sm:px-6 sm:py-14">
        <h1 class="text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl dark:text-white">{{ __('Send feedback') }}</h1>
        <p class="mt-3 {{ $muted }}">{{ __('Report a problem or share an idea. You do not need an e-mail address.') }}</p>

        @if ($errors->any())
            <div class="mt-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900 dark:border-red-800 dark:bg-red-900/20 dark:text-red-100" role="alert" data-feedback-errors>
                <p class="font-semibold">{{ __('Your report was not sent. What you typed is still here.') }}</p>
                <ul class="mt-1 list-disc space-y-0.5 ps-5">
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('feedback.store', $query) }}" enctype="multipart/form-data" class="mt-8 space-y-6"
              data-feedback-form data-max-kb="5120"
              data-msg-type="{{ __('Use a PNG, JPEG or WebP image.') }}" data-msg-size="{{ __('That image is too large. Use one up to 5 MB.') }}"
              @if ($turnstile) x-data="turnstileForm(@js($turnstile))" x-on:submit="handleSubmit($event)" @endif>
            @csrf
            <input type="hidden" name="from" value="{{ $from }}">
            <input type="hidden" name="v" value="{{ $v }}">
            <input type="hidden" name="viewport" value="" data-fb-viewport>
            {{-- A trap for robots: people never see or fill this. --}}
            <div style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden" aria-hidden="true">
                <label>Website <input type="text" name="company_url" value="" tabindex="-1" autocomplete="off"></label>
            </div>

            <div>
                <x-input-label for="fb-app" :value="__('About')" />
                <select id="fb-app" name="app" class="input">
                    @foreach (Feedback::NAMES as $slug => $name)
                        <option value="{{ $slug }}" @selected($chosenApp === $slug)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <fieldset>
                <legend class="label">{{ __('Kind') }}</legend>
                <div class="mt-1 grid gap-2 sm:grid-cols-3" role="radiogroup">
                    @foreach ($kinds as $key => $label)
                        <label class="flex min-h-11 cursor-pointer items-center gap-2 rounded-xl border border-slate-300/80 bg-white/70 px-3 text-sm font-medium text-slate-800 has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50 has-[:checked]:text-indigo-900 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-indigo-500 dark:border-white/15 dark:bg-white/5 dark:text-white/85 dark:has-[:checked]:border-indigo-400 dark:has-[:checked]:bg-indigo-500/15 dark:has-[:checked]:text-white">
                            <input type="radio" name="kind" value="{{ $key }}" required @checked($chosenKind === $key) class="h-4 w-4 accent-indigo-600">
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <div>
                <div class="flex items-baseline justify-between gap-3">
                    <x-input-label for="fb-message" :value="__('Your message')" />
                    <span class="text-xs {{ $muted }}" aria-live="off"><span data-fb-count>{{ number_format(mb_strlen($typed)) }}</span> / 4,000</span>
                </div>
                <textarea id="fb-message" name="message" rows="7" required minlength="10" maxlength="4000" data-fb-message
                          placeholder="{{ __('What happened, or what would you like to see?') }}"
                          class="input h-auto min-h-40 py-3 leading-relaxed">{{ $typed }}</textarea>
            </div>

            <div>
                <span class="label">{{ __('Screenshot (optional)') }}</span>
                <div class="mt-1 rounded-xl border border-dashed border-slate-300 bg-white/50 p-4 dark:border-white/20 dark:bg-white/3">
                    <input id="fb-image" type="file" name="image" accept="image/png,image/jpeg,image/webp" class="block w-full text-sm {{ $muted }} file:me-3 file:rounded-lg file:border-0 file:bg-slate-900 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-white dark:file:bg-white dark:file:text-slate-900" data-fb-file>
                    <p class="mt-2 text-xs {{ $muted }}">{{ __('Choose an image or paste one with Ctrl+V. PNG, JPEG or WebP, up to 5 MB.') }}</p>
                    <div class="mt-3 hidden items-center gap-3" data-fb-preview>
                        <img alt="" class="h-16 w-24 rounded-lg border border-slate-200 object-cover dark:border-white/15" data-fb-thumb>
                        <button type="button" class="btn-secondary btn-sm" data-fb-remove>{{ __('Remove image') }}</button>
                    </div>
                    <p class="mt-2 hidden text-sm text-red-600 dark:text-red-400" role="alert" data-fb-image-error></p>
                </div>
            </div>

            <p class="text-sm {{ $muted }}">
                @if ($member)
                    {{ __('Sending as @:name', ['name' => $member->username]) }}
                @else
                    {{ __('Sending as a guest') }} · <a href="{{ route('login') }}" class="underline underline-offset-2 hover:text-slate-900 dark:hover:text-white">{{ __('Sign in') }}</a>
                @endif
            </p>

            @if ($turnstile)
                <x-turnstile size="flexible" />
            @endif

            {{-- Everything that goes along with the text, said plainly before the button. --}}
            <div class="rounded-xl border border-slate-200/80 bg-slate-100/60 p-4 text-sm dark:border-white/10 dark:bg-white/5" data-fb-sent-box>
                <h2 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('Sent with your report') }}</h2>
                <dl class="mt-2 grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 {{ $muted }}">
                    <dt>{{ __('App and screen') }}</dt><dd class="break-words">{{ $screen }}</dd>
                    @if ($v)
                        <dt>{{ __('App version') }}</dt><dd>{{ $v }}</dd>
                    @endif
                    <dt>{{ __('Browser and screen size') }}</dt><dd class="break-words">{{ $browser ?? __('Unknown') }} · <span data-fb-screen>{{ __('Unknown') }}</span></dd>
                    <dt>{{ __('Language') }}</dt><dd>{{ $locale }}</dd>
                    <dt>{{ __('IP address') }}</dt><dd>{{ $ip }}</dd>
                    @if ($member)
                        <dt>HOP ID</dt><dd>{{ '@'.$member->username }}</dd>
                    @endif
                </dl>
                <p class="mt-3 {{ $muted }}">{!! str_replace(':privacy', $privacy, e(__('Your IP address is deleted after 30 days. A report is deleted 180 days after it is finished, or after 12 months at the latest. Details in :privacy.'))) !!}</p>
                <p class="mt-2 {{ $muted }}">{{ __('Please do not write passwords or other secrets. We read every report but cannot answer each one, because there is no e-mail field.') }}</p>
            </div>

            <button type="submit" class="btn-primary btn-lg w-full sm:w-auto" data-fb-submit>{{ __('Send') }}</button>
        </form>
    </section>
</x-marketing-layout>
