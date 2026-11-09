<x-app-layout>
    <x-slot name="pageTitle">{{ __('Settings') }} · HOP</x-slot>
    @php
        $settingsTabs = [
            'account' => [__('Account'), 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'],
            '2fa' => [__('Two-factor'), 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z'],
            'backup' => [__('Export data'), 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4'],
            'activity' => [__('Activity log'), 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01'],
            'login' => [__('Login history'), 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
            'api' => [__('API tokens'), 'M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4'],
        ];
        $u = auth()->user();
        $actionLabels = ['created' => __('Created'), 'updated' => __('Updated'), 'deleted' => __('Deleted'), 'suspended' => __('Suspended'), 'activated' => __('Activated'), 'login' => __('Login')];
        // Apps with a bearer-token API (config hop.api_docs), for the API tab's wording.
        $apiApps = collect($apps)->filter(fn ($a) => config('hop.api_docs.'.$a->slug))->values();
        $apiScopes = \App\Http\Controllers\ApiTokenController::availableScopes($apiApps->pluck('slug')->all());
        // Sign-in times are shown in the person's timezone (the app's when none or an unknown one is saved), and say so.
        $tz = $u->timezone ?: config('app.timezone');
        try {
            new \DateTimeZone($tz);
        } catch (\Throwable) {
            $tz = config('app.timezone');
        }
        $activityFrom = \App\Support\Input::text(request('activity_from'));
        $activityTo = \App\Support\Input::text(request('activity_to'));
    @endphp

    <x-page-header :title="__('Settings')" :subtitle="__('Your account, security and data.')" />

    <div class="grid gap-4 lg:grid-cols-[13rem_minmax(0,1fr)]" x-data="{
        tab: '{{ session('active_tab', 'account') }}',
        fromHash() {
            // Older links from the apps (LinkHop: #security, #data) and obvious guesses still land on the right tab.
            const aliases = { security: '2fa', 'two-factor': '2fa', data: 'backup', export: 'backup', tokens: 'api', logins: 'login', profile: 'account', password: 'account' };
            const hash = window.location.hash.substring(1);
            const hashTab = aliases[hash] || hash;
            if (@js(array_keys($settingsTabs)).includes(hashTab)) {
                this.tab = hashTab;
            }
        },
        // On a phone the tab row scrolls sideways: bring the active tab into view (opened via #api it sat off-screen).
        reveal() {
            this.$nextTick(() => {
                const row = this.$refs.tabs, active = row && row.querySelector('[aria-selected=true]');
                if (active && row.scrollWidth > row.clientWidth) {
                    // From the rectangles, not offsetLeft: the row is not the tab's offset parent.
                    const a = active.getBoundingClientRect(), r = row.getBoundingClientRect();
                    row.scrollLeft += a.left - r.left - (r.width - a.width) / 2;
                }
            });
        },
        init() {
            this.fromHash();
            this.reveal();
            // The row gets wider when the web font arrives, which moves the tabs: place it again then.
            document.fonts?.ready.then(() => this.reveal());
            this.$watch('tab', value => { history.replaceState(null, '', '#' + value); this.reveal(); });
        }
    }" @tab-changed.window="tab = $event.detail" @hashchange.window="fromHash()">
        <nav x-ref="tabs" role="tablist" class="-mx-1 flex gap-1 overflow-x-auto px-1 pb-1 lg:sticky lg:top-24 lg:mx-0 lg:flex-col lg:self-start lg:overflow-visible lg:px-0" aria-label="{{ __('Settings sections') }}">
            @foreach ($settingsTabs as $key => [$label, $icon])
                <button type="button" role="tab" @click="tab = '{{ $key }}'" class="nav-item min-h-11 shrink-0 whitespace-nowrap" :class="tab === '{{ $key }}' && 'nav-item-active'" :aria-selected="tab === '{{ $key }}' ? 'true' : 'false'" :tabindex="tab === '{{ $key }}' ? 0 : -1">
                    <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
                    {{ $label }}
                </button>
            @endforeach
        </nav>
        <div class="min-w-0 space-y-4">
            <x-flash />
            <div class="glass rounded-2xl">
                <div x-show="tab === 'account'" class="divide-glass">
                    @if ($u->isSuperAdmin())
                        <p class="p-5" role="status">{{ __('Superadmin accounts are permanent and read-only, including for the account itself.') }}</p>
                        <dl class="p-5"><dt>{{ __('Name') }}</dt><dd>{{ $u->name }}</dd><dt>{{ __('Email') }}</dt><dd>{{ $u->email }}</dd></dl>
                    @else
                    {{-- Each setting is a row: what it is on the left, the fields on the right. --}}
                    <section class="grid gap-4 p-5 md:grid-cols-3 md:gap-8">
                        <div>
                            <h3 class="section-title">{{ __('HOP ID profile') }}</h3>
                            <p class="mt-1 text-xs text-muted">{{ __('One profile for every HOP app: :apps. Changing the email asks you to verify it again.', ['apps' => collect($apps)->pluck('name')->join(', ')]) }}</p>
                        </div>
                        <div class="space-y-5 md:col-span-2">
                            <div class="flex items-center gap-4">
                                @if ($u->avatarUrl())
                                    <img src="{{ $u->avatarUrl() }}" alt="" class="h-16 w-16 rounded-full object-cover ring-1 ring-slate-500/20">
                                @else
                                    <div class="flex h-16 w-16 items-center justify-center rounded-full bg-slate-500/15 text-xl font-semibold text-ink">{{ mb_strtoupper(mb_substr($u->name, 0, 1)) }}</div>
                                @endif
                                <form method="POST" action="{{ route('profile.avatar') }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-2">
                                    @csrf
                                    <label class="btn-soft btn-sm cursor-pointer">
                                        <input type="file" name="avatar" accept="image/png,image/jpeg,image/webp,image/gif" class="sr-only" @change="$el.form.requestSubmit()">
                                        {{ __('Change photo') }}
                                    </label>
                                    <button type="submit" class="sr-only">{{ __('Save') }}</button>
                                    <span class="text-xs text-muted">{{ __('JPG, PNG or WebP, up to :max. Shown as a 400×400 square.', ['max' => \App\Support\Avatar::maxLabel()]) }}</span>
                                </form>
                                @if ($u->avatar_path)
                                    <form method="POST" action="{{ route('profile.avatar.destroy') }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-ghost btn-sm">{{ __('Remove') }}</button>
                                    </form>
                                @endif
                            </div>
                            @error('avatar')<p class="text-sm text-rose-600">{{ $message }}</p>@enderror
                            <form method="POST" action="{{ route('profile.update') }}" class="space-y-4"
                                  x-data="{
                                      current: @js((string) $u->username), value: @js((string) $u->username), ok: null,
                                      message: @js(__('Your handle in every HOP app: 3 to 30 lowercase letters, numbers, dots, dashes or underscores.')),
                                      async check() {
                                          const v = (this.value || '').trim().toLowerCase().replace(/^@/, '');
                                          if (v === this.current) { this.ok = null; this.message = @js(__('This is your current username.')); return; }
                                          try {
                                              const r = await fetch(@js(route('profile.username-check')) + '?u=' + encodeURIComponent(v), { headers: { 'Accept': 'application/json' } });
                                              const d = await r.json();
                                              this.ok = d.ok; this.message = d.ok ? @js(__(':username is available.')).replace(':username', '@' + d.username) : d.message;
                                          } catch (e) { this.ok = null; }
                                      },
                                  }">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="_profile_form" value="1">
                                <input type="hidden" name="_back" value="{{ route('settings', [], false) }}#account">
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <label for="fsettin-1" class="label">{{ __('Name') }}</label>
                                        <input id="fsettin-1" type="text" name="name" value="{{ \App\Support\Input::text(old('name', $u->name)) }}" class="input" required autocomplete="name">
                                        @error('name')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                                    </div>
                                    <div>
                                        <label for="fsettin-3" class="label">{{ __('Username') }}</label>
                                        <div class="relative">
                                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-muted">@</span>
                                            <input id="fsettin-3" type="text" name="username" value="{{ \App\Support\Input::text(old('username', $u->username)) }}" class="input pl-7" required autocomplete="username" minlength="3" maxlength="30" x-model="value" @input.debounce.400ms="check()" spellcheck="false">
                                        </div>
                                        <p class="mt-1 text-xs" :class="ok === false ? 'text-rose-600' : 'text-muted'" x-text="message">{{ __('Your handle in every HOP app: 3 to 30 lowercase letters, numbers, dots, dashes or underscores.') }}</p>
                                        @error('username')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                                    </div>
                                    <div>
                                        <label for="fsettin-2" class="label">{{ __('Email') }}</label>
                                        <input id="fsettin-2" type="email" name="email" value="{{ \App\Support\Input::text(old('email', $u->email)) }}" class="input" required autocomplete="email">
                                        @error('email')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                                    </div>
                                    <div>
                                        <label for="fsettin-4" class="label">{{ __('Location') }} <span class="text-muted">({{ __('optional') }})</span></label>
                                        <input id="fsettin-4" type="text" name="location" value="{{ \App\Support\Input::text(old('location', $u->location)) }}" class="input" maxlength="100" autocomplete="address-level2">
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label for="fsettin-5" class="label">{{ __('Website') }} <span class="text-muted">({{ __('optional') }})</span></label>
                                        <input id="fsettin-5" type="text" name="website" value="{{ \App\Support\Input::text(old('website', $u->website)) }}" class="input" maxlength="200" placeholder="https://" autocomplete="url">
                                        @error('website')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label for="fsettin-6" class="label">{{ __('Bio') }} <span class="text-muted">({{ __('optional') }})</span></label>
                                        <textarea id="fsettin-6" name="bio" rows="3" maxlength="300" class="input">{{ \App\Support\Input::text(old('bio', $u->bio)) }}</textarea>
                                        @error('bio')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                                    </div>
                                    <label class="flex items-start gap-3 sm:col-span-2">
                                        <input type="checkbox" name="show_owner" value="1" class="mt-0.5 rounded" @checked(old('show_owner', $u->show_owner))>
                                        <span class="text-sm text-ink">{{ __('Show my name and photo on what I share in the apps') }}<span class="block text-xs text-muted">{{ __("Off: images, notes and pages you share don't say who made them.") }}</span></span>
                                    </label>
                                </div>
                                <div class="flex justify-end">
                                    <button type="submit" class="btn-primary btn-sm">{{ __('Save profile') }}</button>
                                </div>
                            </form>
                        </div>
                    </section>
                    <section class="grid gap-4 p-5 md:grid-cols-3 md:gap-8">
                        <div>
                            <h3 class="section-title">{{ __('Password') }}</h3>
                            <p class="mt-1 text-xs text-muted">{{ __("Use a long, random password you don't use anywhere else.") }}</p>
                        </div>
                        <form method="POST" action="{{ route('password.update') }}" class="space-y-4 md:col-span-2">
                            @csrf
                            @method('PUT')
                            @if ($errors->updatePassword->any())
                                <ul class="glass-inset list-disc space-y-0.5 border-rose-400/40 py-2.5 pl-8 pr-3 text-sm text-rose-700 dark:text-rose-300">
                                    @foreach ($errors->updatePassword->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            @endif
                            <div>
                                <label for="fsettin-pw-3" class="label">{{ __('Current password') }}</label>
                                <input id="fsettin-pw-3" type="password" name="current_password" class="input sm:max-w-[calc(50%-0.5rem)]" required autocomplete="current-password">
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label for="fsettin-pw-4" class="label">{{ __('New password') }}</label>
                                    <input id="fsettin-pw-4" type="password" name="password" class="input" required autocomplete="new-password">
                                </div>
                                <div>
                                    <label for="fsettin-pw-5" class="label">{{ __('Confirm new password') }}</label>
                                    <input id="fsettin-pw-5" type="password" name="password_confirmation" class="input" required autocomplete="new-password">
                                </div>
                            </div>
                            <div class="flex justify-end">
                                <button type="submit" class="btn-primary btn-sm">{{ __('Update password') }}</button>
                            </div>
                        </form>
                    </section>
                    @unless($u->isSuperAdmin())
                        <section class="grid gap-4 p-5 md:grid-cols-3 md:gap-8" x-data="{
                            showModal: false,
                            password: '',
                            countdown: 10,
                            canDelete: false,
                            startCountdown() {
                                this.countdown = 10;
                                this.canDelete = false;
                                const interval = setInterval(() => {
                                    this.countdown--;
                                    if (this.countdown <= 0) {
                                        this.canDelete = true;
                                        clearInterval(interval);
                                    }
                                }, 1000);
                            }
                        }">
                            <div>
                                <h3 class="section-title text-rose-600 dark:text-rose-400">{{ __('Delete account') }}</h3>
                                <p class="mt-1 text-xs text-muted">{{ __('Removes your HOP ID and everything you made in the apps (links, bio pages, images, notes, folders, tags, analytics) for good.') }}</p>
                            </div>
                            <div class="flex items-start md:col-span-2 md:justify-end">
                                <button type="button" @click="showModal = true; $nextTick(() => startCountdown())" class="btn-danger btn-sm">{{ __('Delete my account') }}</button>
                            </div>
                            <div x-show="showModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4 backdrop-blur-xs" @click.self="showModal = false; password = ''" @keydown.escape.window="showModal = false; password = ''">
                                <div class="glass-strong w-full max-w-md rounded-2xl p-5" role="dialog" aria-modal="true" aria-labelledby="delete-account-title">
                                    <h3 id="delete-account-title" class="text-base font-semibold text-rose-600 dark:text-rose-400">{{ __('Delete account') }}</h3>
                                    <p class="mt-1 text-sm text-muted"><strong class="text-ink">{{ __('This is permanent.') }}</strong> {{ __('Your links, bio pages, images, notes, folders, tags and analytics are deleted together with your HOP ID.') }}</p>
                                    <form method="POST" action="{{ route('profile.destroy') }}" class="mt-4 space-y-3">
                                        @csrf
                                        @method('DELETE')
                                        <div>
                                            <label for="fsettin-pw-6" class="label">{{ __('Confirm your password') }}</label>
                                            <input id="fsettin-pw-6" type="password" name="password" x-model="password" class="input" required autocomplete="current-password">
                                        </div>
                                        <p class="text-center text-xs text-muted">
                                            <span x-show="!canDelete">{!! str_replace('@@S@@', '<span x-text="countdown" class="font-semibold tabular-nums text-ink"></span>', e(__('You can confirm in :seconds s', ['seconds' => '@@S@@']))) !!}</span>
                                            <span x-show="canDelete">{{ __('You can now delete your account.') }}</span>
                                        </p>
                                        <div class="flex gap-2">
                                            <button type="button" @click="showModal = false; password = ''" class="btn-soft flex-1">{{ __('Cancel') }}</button>
                                            <button type="submit" :disabled="!canDelete || !password" class="btn-danger flex-1">{{ __('Delete forever') }}</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </section>
                    @endunless
                    @endif
                </div>
                <div x-show="tab === '2fa'" x-cloak class="space-y-4 p-5">
                    @if ($u->isSuperAdmin())
                        <p role="status">{{ __('Superadmin accounts are permanent and read-only, including for the account itself.') }}</p>
                    @else
                    <div>
                        <h3 class="text-base font-semibold text-ink">{{ __('Two-factor authentication') }}</h3>
                        <p class="text-sm text-muted">{{ __('A code from your phone at sign-in, on top of your password.') }}</p>
                    </div>
                    @if(!$u->two_factor_secret)
                        <div class="glass-inset rounded-xl p-4">
                            <h4 class="font-medium text-ink">{{ __('Turn on two-factor') }}</h4>
                            <p class="mt-1 text-sm text-muted">
                                {{ __('Each sign-in then asks for a code from your authenticator app. You also get recovery codes for when the phone is not at hand.') }}
                            </p>
                            <form method="POST" action="{{ route('settings.2fa.enable') }}" class="mt-4 space-y-3">
                                @csrf
                                <div>
                                    <label for="fsettin-7" class="label">{{ __('Confirm your password') }}</label>
                                    <input id="fsettin-7" type="password" name="password" class="input mt-1" required autocomplete="current-password">
                                </div>
                                <button type="submit" class="btn-primary btn-sm">
                                    {{ __('Enable 2FA') }}
                                </button>
                            </form>
                        </div>
                    @else
                        @if(!$u->two_factor_confirmed_at)
                            <div class="rounded-xl border border-indigo-500/30 bg-indigo-500/10 p-4">
                                <h4 class="font-medium text-ink">{{ __('Finish the setup') }}</h4>
                                <p class="mt-1 text-sm text-muted">
                                    {{ __('Scan the QR code with your authenticator app (Google Authenticator, Authy, Aegis, …), then enter the code it shows.') }}
                                </p>
                                <div class="mt-4 flex items-center justify-center bg-white p-4 rounded-lg">
                                    @php
                                        $google2fa = new \PragmaRX\Google2FA\Google2FA();
                                        $secret = decrypt($u->two_factor_secret);
                                        $qrCodeUrl = $google2fa->getQRCodeUrl(
                                            config('app.name'),
                                            $u->email,
                                            $secret
                                        );
                                    @endphp
                                    {!! QrCode::size(200)->generate($qrCodeUrl) !!}
                                </div>
                                <div class="glass-inset mt-4 rounded-xl p-3">
                                    <p class="text-center text-xs text-muted">{{ __('Secret key (manual entry)') }}</p>
                                    <p class="text-center font-mono text-sm text-ink">{{ $secret }}</p>
                                </div>
                                <form method="POST" action="{{ route('settings.2fa.confirm') }}" class="mt-4 space-y-3">
                                    @csrf
                                    <div>
                                        <label for="fsettin-8" class="label">{{ __('Enter the 6-digit code from your app') }}</label>
                                        <input id="fsettin-8" type="text" name="code" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" autocomplete="one-time-code" class="input mt-1" required>
                                    </div>
                                    <button type="submit" class="btn-primary btn-sm">
                                        {{ __('Confirm and activate') }}
                                    </button>
                                </form>
                            </div>
                        @else
                            <div class="rounded-lg border border-green-200 bg-green-50 p-4 dark:border-green-900/30 dark:bg-green-900/20">
                                <div class="flex items-center gap-2">
                                    <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    <h4 class="font-medium text-green-900 dark:text-green-100">{{ __('Two-factor is on') }}</h4>
                                </div>
                                <p class="mt-1 text-sm text-green-800 dark:text-green-200">
                                    {{ __('Your account asks for a code from your authenticator app at every sign-in.') }}
                                </p>
                            </div>
                            <div class="glass-inset rounded-xl p-4">
                                <h4 class="font-medium text-ink">{{ __('Recovery codes') }}</h4>
                                <p class="mt-1 text-sm text-muted">
                                    {{ __('Keep these codes somewhere safe. Each one signs you in once if you lose your phone.') }}
                                </p>
                                @php
                                    $recoveryCodes = json_decode(decrypt($u->two_factor_recovery_codes), true);
                                @endphp
                                <div class="glass-inset mt-4 grid grid-cols-2 gap-2 rounded-xl p-3">
                                    @foreach($recoveryCodes as $code)
                                        <div class="font-mono text-sm tabular-nums text-ink">{{ $code }}</div>
                                    @endforeach
                                </div>
                                <form method="POST" action="{{ route('settings.2fa.recovery-codes') }}" class="mt-4" data-confirm="{{ __('Make new recovery codes? The old ones stop working.') }}">
                                    @csrf
                                    <input type="hidden" name="password" value="">
                                    <button type="button" data-prompt-password="{{ __('Enter your password to make new recovery codes:') }}" class="btn-soft btn-sm">
                                        {{ __('Make new codes') }}
                                    </button>
                                </form>
                            </div>
                            <div class="rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-900/30 dark:bg-red-900/20">
                                <h4 class="font-medium text-red-900 dark:text-red-100">{{ __('Turn off two-factor') }}</h4>
                                <p class="mt-1 text-sm text-red-800 dark:text-red-200">
                                    {{ __('Your password alone then signs you in. The account is less protected without it.') }}
                                </p>
                                <form method="POST" action="{{ route('settings.2fa.disable') }}" class="mt-4" data-confirm="{{ __('Turn off two-factor authentication?') }}">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="password" value="">
                                    <button type="button" data-prompt-password="{{ __('Enter your password to turn off two-factor:') }}" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">
                                        {{ __('Disable 2FA') }}
                                    </button>
                                </form>
                            </div>
                        @endif
                    @endif
                    @endif
                </div>
                <div x-show="tab === 'backup'" x-cloak class="space-y-4 p-5">
                    <div>
                        <h3 class="text-base font-semibold text-ink">{{ __('Export data') }}</h3>
                        <p class="text-sm text-muted">{{ __('Your account lives here; your content lives in each app. Download both.') }}</p>
                    </div>
                    <div class="glass-inset rounded-xl p-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h4 class="font-medium text-ink">{{ __('Account data (JSON)') }}</h4>
                                <p class="mt-1 text-sm text-muted">{{ __('Profile, roles, recent sign-ins, activity log and API token names.') }}</p>
                            </div>
                            <form method="POST" action="{{ route('settings.export.account') }}">
                                @csrf
                                <button type="submit" class="btn-primary btn-sm">{{ __('Download JSON') }}</button>
                            </form>
                        </div>
                    </div>
                    @foreach ($apps as $app)
                        @if ($app->exportUrl())
                            <div class="glass-inset flex flex-wrap items-center justify-between gap-3 rounded-xl p-4">
                                <div>
                                    <h4 class="font-medium text-ink">{{ $app->name }}</h4>
                                    <p class="mt-1 text-sm text-muted">{{ __($app->tagline) }}</p>
                                </div>
                                <a href="{{ $app->exportUrl() }}" class="btn-soft btn-sm">{{ __('Export in :app', ['app' => $app->name]) }}</a>
                            </div>
                        @elseif ($note = config('hop.export_notes.'.$app->slug))
                            <div class="glass-inset flex flex-wrap items-center justify-between gap-3 rounded-xl p-4">
                                <div>
                                    <h4 class="font-medium text-ink">{{ $app->name }}</h4>
                                    <p class="mt-1 text-sm text-muted">{{ __($note) }}</p>
                                </div>
                                <span class="text-xs text-muted">{{ __('Nothing to export') }}</span>
                            </div>
                        @endif
                    @endforeach
                </div>
                <div x-show="tab === 'activity'" x-cloak class="space-y-4 p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-ink">{{ __('Activity log') }}</h3>
                            <p class="text-sm text-muted">{{ __('The latest 50 actions on your account. Pick dates to see older ones.') }}</p>
                        </div>
                    </div>
                    <form method="GET" action="{{ route('settings') }}#activity" class="glass-inset rounded-xl p-4">
                        <input type="hidden" name="tab" value="activity">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="fsettin-15" class="label">{{ __('From date') }}</label>
                                <input id="fsettin-15" type="date" name="activity_from" value="{{ $activityFrom }}" class="input mt-1">
                            </div>
                            <div>
                                <label for="fsettin-16" class="label">{{ __('To date') }}</label>
                                <input id="fsettin-16" type="date" name="activity_to" value="{{ $activityTo }}" class="input mt-1">
                            </div>
                        </div>
                        @if ($activityFrom && $activityTo && $activityFrom > $activityTo)
                            <p class="mt-3 text-sm text-rose-600 dark:text-rose-400" role="alert">{{ __('The start date is after the end date, so nothing matches.') }}</p>
                        @endif
                        <div class="mt-3 flex gap-2">
                            <button type="submit" class="btn-primary btn-sm">
                                {{ __('Apply filter') }}
                            </button>
                            <a href="{{ route('settings') }}#activity" class="btn-ghost btn-sm">
                                {{ __('Clear') }}
                            </a>
                        </div>
                    </form>
                    <div class="space-y-2">
                        @forelse ($activityLogs as $log)
                            <div class="glass-inset rounded-xl p-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="break-words text-sm font-medium text-ink">{{ $log->description }}</p>
                                        <p class="text-xs tabular-nums text-muted">{{ \Carbon\Carbon::parse($log->created_at)->format('M d, Y H:i:s') }}</p>
                                    </div>
                                    <span class="chip-gray shrink-0">
                                        {{ $actionLabels[$log->action] ?? ucfirst($log->action) }}
                                    </span>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-muted">{{ __('No activity in this period.') }}</p>
                        @endforelse
                    </div>
                </div>
                <div x-show="tab === 'login'" x-cloak class="space-y-4 p-5">
                    <div>
                        <h3 class="text-base font-semibold text-ink">{{ __('Login history') }}</h3>
                        <p class="text-sm text-muted">{{ __('Your last 5 sign-ins') }} · {{ __('Times in :zone', ['zone' => $tz]) }}</p>
                    </div>
                    @forelse($loginHistories as $index => $history)
                        <div class="glass-inset rounded-xl p-4">
                            <div class="flex items-start justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-indigo-500/15 text-sm font-medium tabular-nums text-indigo-600 dark:text-indigo-300">
                                        {{ $index + 1 }}
                                    </span>
                                    <div>
                                        <p class="text-sm font-medium tabular-nums text-ink">
                                            {{ \Carbon\Carbon::parse($history->logged_in_at)->timezone($tz)->format('M d, Y - H:i:s') }}
                                        </p>
                                        <p class="text-xs text-muted">{{ \Carbon\Carbon::parse($history->logged_in_at)->diffForHumans() }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    @if($history->is_proxy)
                                        <span class="rounded-full bg-red-100 px-2 py-1 text-xs font-medium text-red-700 dark:bg-red-900/30 dark:text-red-400" title="{{ __('Proxy confidence: :percent%', ['percent' => $history->proxy_confidence]) }}">
                                            ⚠ {{ ucfirst($history->proxy_type) }}
                                        </span>
                                    @endif
                                    @if($index === 0)
                                        <span class="rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400">
                                            {{ __('Current') }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <div class="mt-3 grid grid-cols-2 md:grid-cols-4 gap-3 text-xs">
                                <div>
                                    <p class="font-medium text-muted">{{ __('IP address') }}</p>
                                    <p class="text-ink">{{ $history->ip_address ?? __('Unknown') }}</p>
                                </div>
                                <div>
                                    <p class="font-medium text-muted">{{ __('Location') }}</p>
                                    <p class="text-ink">
                                        @if($history->city && $history->country)
                                            {!! country_flag($history->country) !!} {{ $history->city }}, {{ country_name($history->country) }}
                                        @elseif($history->country)
                                            {!! country_flag($history->country) !!} {{ country_name($history->country) }}
                                        @else
                                            {{ __('Unknown') }}
                                        @endif
                                    </p>
                                </div>
                                <div>
                                    <p class="font-medium text-muted">{{ __('Provider') }}</p>
                                    <p class="text-ink truncate" title="{{ $history->isp ?? $history->provider ?? __('Unknown') }}">
                                        {{ \Illuminate\Support\Str::limit($history->isp ?? $history->provider ?? __('Unknown'), 20) }}
                                    </p>
                                </div>
                                <div>
                                    <p class="font-medium text-muted">{{ __('Device') }}</p>
                                    <p class="text-ink">{{ $history->device ?? __('Desktop') }}</p>
                                </div>
                                <div>
                                    <p class="font-medium text-muted">{{ __('Browser') }}</p>
                                    <p class="text-ink">{{ $history->browser ?? __('Unknown') }}</p>
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-muted">{{ __('No sign-ins recorded yet.') }}</p>
                    @endforelse
                    {{-- Opening an app through the hub is a hand-off, not a new sign-in: kept in its own folded list so it never hides the sign-ins above. --}}
                    @if ($appSignIns->isNotEmpty())
                        <details class="glass-inset rounded-xl">
                            <summary class="flex min-h-11 cursor-pointer items-center justify-between gap-3 px-4 py-2 text-sm font-medium text-ink">
                                <span>{{ __('App sign-ins') }}</span>
                                <span class="text-xs font-normal tabular-nums text-muted">{{ $appSignIns->count() }}</span>
                            </summary>
                            <div class="px-4 pb-4">
                                <p class="text-xs text-muted">{{ __('Each time you open an app from here, the hub signs you in to it. These are not new sign-ins to your account.') }}</p>
                                <ul class="mt-2 divide-y divide-slate-500/10">
                                    @foreach ($appSignIns as $handoff)
                                        <li class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-0.5 py-2 text-sm">
                                            <span class="min-w-0 truncate font-medium text-ink">{{ $handoff->provider ?? __('Unknown') }}</span>
                                            <time class="shrink-0 text-xs tabular-nums text-muted" datetime="{{ $handoff->logged_in_at->toIso8601String() }}">{{ $handoff->logged_in_at->timezone($tz)->format('M d, Y - H:i') }}</time>
                                            <span class="w-full truncate text-xs text-muted">{{ collect([$handoff->device, $handoff->browser, $handoff->ip_address])->filter()->implode(' · ') ?: __('Unknown device') }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </details>
                    @endif
                </div>
                <!-- API tokens -->
                <div x-show="tab === 'api'" x-cloak x-data="apiSettings()" class="space-y-4 p-5">
                    @if ($u->isSuperAdmin())
                        <p role="status">{{ __('Superadmin accounts are permanent and read-only, including for the account itself.') }}</p>
                    @else
                    <div>
                        <h3 class="text-base font-semibold text-ink">{{ __('API tokens') }}</h3>
                        <p class="text-sm text-muted">{{ __('One token works at every HOP API (:apps) and is limited to the scopes you pick.', ['apps' => $apiApps->pluck('name')->join(', ')]) }}</p>
                    </div>
                    <div class="glass-inset rounded-xl p-4">
                        <h4 class="text-sm font-semibold text-ink mb-3">{{ __('Create a token') }}</h4>
                        <form method="POST" action="{{ route('api-tokens.store') }}" @submit.prevent="createToken" class="space-y-3">
                            @csrf
                            <div>
                                <label for="fsettin-17" class="label">{{ __('Token name') }}</label>
                                <input id="fsettin-17"
                                    type="text"
                                    name="name"
                                    x-model="newTokenName"
                                    placeholder="{{ __('ShareX, my script, …') }}"
                                    required
                                    class="input"
                                >
                                <p class="mt-1 text-xs text-muted">{{ __('A name that tells you where the token is used.') }}</p>
                            </div>
                            <fieldset>
                                <legend class="label">{{ __('Scopes (none checked: everything you can do)') }}</legend>
                                <div class="mt-1 grid gap-1 sm:grid-cols-2">
                                    @foreach ($apiScopes as $scope => $what)
                                        <label class="flex items-center gap-2 text-sm text-ink"><input type="checkbox" name="scopes[]" value="{{ $scope }}"> <code class="text-xs">{{ $scope }}</code> <span class="text-xs text-muted">{{ __($what) }}</span></label>
                                    @endforeach
                                </div>
                            </fieldset>
                            <button type="submit" class="btn-primary btn-sm" :disabled="isCreating">
                                <span x-show="!isCreating">{{ __('Generate token') }}</span>
                                <span x-show="isCreating" x-cloak>{{ __('Generating…') }}</span>
                            </button>
                        </form>
                    </div>
                    <!-- The new token, shown once -->
                    <div x-show="newlyCreatedToken" x-cloak class="rounded-xl border border-emerald-300 bg-emerald-50 p-4 dark:border-emerald-700 dark:bg-emerald-900/20" role="status">
                        <h4 class="text-sm font-semibold text-emerald-900 dark:text-emerald-200 mb-2">{{ __('Token created') }}</h4>
                        <p class="text-xs text-emerald-800 dark:text-emerald-300 mb-3">{{ __("Copy it now: it is shown only this once.") }}</p>
                        <div class="flex gap-2">
                            <input
                                type="text"
                                :value="newlyCreatedToken"
                                readonly
                                aria-label="{{ __('New token') }}"
                                class="min-w-0 flex-1 rounded-lg border-emerald-300 bg-white px-3 py-2 text-sm font-mono text-emerald-900 dark:border-emerald-600 dark:bg-emerald-950 dark:text-emerald-100"
                            >
                            <button
                                type="button"
                                @click="copyToken(newlyCreatedToken)"
                                class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500"
                            >
                                <span x-show="!copied">{{ __('Copy') }}</span>
                                <span x-show="copied" x-cloak>{{ __('Copied!') }}</span>
                            </button>
                        </div>
                    </div>
                    <!-- Existing tokens -->
                    <div>
                        <h4 class="text-sm font-semibold text-ink mb-3">{{ __('Your API tokens') }}</h4>
                        <div class="space-y-3">
                            @forelse($u->apiTokens as $token)
                                {{-- Wraps at narrow widths: the meta line breaks per item and Revoke drops under it instead of leaving the card (375 px). --}}
                                <div class="glass-inset flex flex-wrap items-start justify-between gap-3 rounded-xl p-4">
                                    <div class="min-w-0 flex-1 basis-56">
                                        <h5 class="truncate font-semibold text-ink">{{ $token->name }}</h5>
                                        <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted">
                                            <span class="tabular-nums">{{ __('Created :date', ['date' => $token->created_at->format('M d, Y')]) }}</span>
                                            @if($token->last_used_at)
                                                <span>{{ __('Last used :when', ['when' => $token->last_used_at->diffForHumans()]) }}</span>
                                            @else
                                                <span class="text-amber-600 dark:text-amber-400">{{ __('Never used') }}</span>
                                            @endif
                                            <span class="tabular-nums">{{ __(':count requests per hour', ['count' => $token->rate_limit]) }}</span>
                                            <span class="min-w-0 break-words">{{ __('Scopes: :scopes', ['scopes' => $token->scopes ? implode(', ', $token->scopes) : __('all')]) }}</span>
                                        </div>
                                    </div>
                                    <form method="POST" action="{{ route('api-tokens.destroy', $token) }}" class="shrink-0" data-confirm="{{ __('Revoke the token “:name”? Anything using it stops working at once.', ['name' => $token->name]) }}" data-danger data-subject="{{ $token->name }}" data-confirm-title="{{ __('Revoke') }}" data-confirm-ok="{{ __('Revoke') }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="inline-flex min-h-11 items-center rounded-lg border border-red-300 px-3 text-sm font-medium text-red-600 transition hover:bg-red-50 active:scale-[0.97] dark:border-red-700 dark:text-red-400 dark:hover:bg-red-900/20">
                                            {{ __('Revoke') }}
                                        </button>
                                    </form>
                                </div>
                            @empty
                                <p class="text-sm text-muted">{{ __('No API tokens yet.') }}</p>
                            @endforelse
                        </div>
                    </div>
                    <!-- API documentation -->
                    <div class="rounded-xl border border-indigo-500/30 bg-indigo-500/10 p-4">
                        <h4 class="section-title mb-1">{{ __('API documentation') }}</h4>
                        <p class="mb-3 text-sm text-muted">{{ __("Where each app's API lives, what the scopes mean and the rate limit.") }}</p>
                        <a href="{{ route('api-docs') }}" class="btn-primary btn-sm">
                            {{ __('View API docs') }}
                        </a>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    <script nonce="{{ Vite::cspNonce() }}">
    function apiSettings() {
        return {
            newTokenName: '',
            newlyCreatedToken: '',
            isCreating: false,
            copied: false,
            async createToken(event) {
                this.isCreating = true;
                try {
                    const formData = new FormData(event.target);
                    const response = await fetch('{{ route("api-tokens.store") }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json',
                        },
                        body: formData
                    });
                    const data = await response.json();
                    if (response.ok && data.token) {
                        this.newlyCreatedToken = data.token;
                        this.newTokenName = '';
                        // Reload after 10 seconds so the new token shows in the list
                        setTimeout(() => {
                            window.location.reload();
                        }, 10000);
                    } else {
                        window.HopDialogs.alert({ message: @js(__('Could not create the token: :message')).replace(':message', data.message || @js(__('Unknown error'))) });
                    }
                } catch (error) {
                    console.error('Error creating token:', error);
                    window.HopDialogs.alert({ message: @js(__('Something went wrong while creating the token.')) });
                } finally {
                    this.isCreating = false;
                }
            },
            copyToken(token) {
                navigator.clipboard.writeText(token).then(() => {
                    this.copied = true;
                    setTimeout(() => {
                        this.copied = false;
                    }, 2000);
                });
            }
        }
    }
    </script>
</x-app-layout>
