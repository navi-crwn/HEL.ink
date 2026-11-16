<?php

namespace App\Http\Controllers;

use App\Models\FeedbackReport;
use App\Services\TurnstileService;
use App\Services\UserAgentService;
use App\Support\Feedback;
use App\Support\FeedbackImage;
use App\Support\FeedbackImageRejected;
use App\Support\HopApps;
use App\Support\Input;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Illuminate\View\View;

/**
 * The one Feedback form of the HOP family. The apps only link here; reports are kept here and read in Imperium
 * (HopFeedbackController). Guests and members may send; nobody can read a report through the web.
 */
class FeedbackController extends Controller
{
    public const GUEST_PER_HOUR = 3;

    public const GUEST_PER_DAY = 10;

    public const MEMBER_PER_HOUR = 10;

    public function __construct(private readonly TurnstileService $turnstile) {}

    /** GET /feedback?app=&from=&v= : parameters that are not valid are dropped, never an error. */
    public function create(Request $request): View
    {
        $this->ensureOn();
        $too = Input::text($request->query('e')) === 'size';

        return $this->form($request, $this->params($request->query()), $too ? ['image' => __('That image is too large. Use one up to 5 MB.')] : []);
    }

    /** POST /feedback */
    public function store(Request $request): RedirectResponse|Response
    {
        $this->ensureOn();
        $user = $request->user();
        $params = $this->params($request->all() + $request->query());

        // The trap field is invisible to people: a robot that fills it is answered as if it worked, and nothing is kept.
        if (filled($request->input('company_url'))) {
            return $this->redirectSent($params['app'], false);
        }

        // What was typed must be text before any work is done: bytes that are not UTF-8 would be refused by the database.
        $raw = Input::text($request->input('message'));
        if (! mb_check_encoding($raw, 'UTF-8')) {
            return back()->withInput($request->only('kind', 'app'))->withErrors(['message' => __('That text could not be read. Remove unusual characters and try again.')]);
        }
        // Control characters other than new lines and tabs have no place in a report.
        $message = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', str_replace(["\r\n", "\r"], "\n", $raw)) ?? '');
        $validator = Validator::make(['kind' => $request->input('kind'), 'message' => $message], [
            'kind' => ['required', 'string', 'in:'.implode(',', FeedbackReport::KINDS)],
            'message' => ['required', 'string', 'min:10', 'max:4000'],
        ], [
            'kind.required' => __('Choose what kind of feedback this is.'),
            'kind.in' => __('Choose what kind of feedback this is.'),
            'message.required' => __('Write at least 10 characters.'),
            'message.min' => __('Write at least 10 characters.'),
            'message.max' => __('Keep it to 4,000 characters or fewer.'),
        ]);
        $back = fn (array $errors) => back()->withInput($request->only('kind', 'message', 'app'))->withErrors($errors);
        if ($validator->fails()) {
            return $back($validator->errors()->toArray());
        }

        // Send limits count attempts from here on (a refusal for a typing mistake above is free; the security check and the
        // picture are the expensive parts): a guest by address (an IPv6 visitor by its /64), a member by account.
        $address = self::addressKey((string) $request->ip());
        $keys = $user
            ? [['feedback:u:'.$user->id.':h', self::MEMBER_PER_HOUR, 3600]]
            : [['feedback:g:'.$address.':h', self::GUEST_PER_HOUR, 3600], ['feedback:g:'.$address.':d', self::GUEST_PER_DAY, 86400]];
        foreach ($keys as [$key, $max, $decay]) {
            if (RateLimiter::hit($key, $decay) > $max) {
                return response()->view('feedback.create', $this->viewData($request, $params, $request->only('kind', 'message'))
                    + ['errors' => $this->bag(['limit' => __('You have sent a lot of feedback already. Please try again later.')])], 429)
                    ->header('Retry-After', (string) RateLimiter::availableIn($key));
            }
        }

        if (! $user && ! $this->turnstile->verify($request->input('cf-turnstile-response'), $request->ip())) {
            // Not named turnstile: the check's own widget would print this message a second time, away from the top.
            return $back(['security' => __('The security check failed. Please try again.')]);
        }

        // The screenshot is checked and drawn again before anything is saved; a refused one keeps the typed text.
        $path = null;
        $noRoom = false;
        $file = $request->file('image');
        if (is_array($file)) {
            return $back(['image' => __('That file could not be read as an image.')]);
        }
        if ($file !== null) {
            try {
                $path = FeedbackImage::store($file);
            } catch (FeedbackImageRejected $e) {
                if ($e->reason !== 'no_room') {
                    return $back(['image' => $this->imageMessage($e->reason)]);
                }
                $noRoom = true; // the report is kept without its image, and the thank-you page says so
            }
        }

        // The same sender with the same text within a minute is one report, not two.
        $sender = $user ? 'u'.$user->id : 'g'.$request->ip();
        $dup = 'feedback:dup:'.sha1($sender."\n".$message);
        if (! Cache::add($dup, 1, 60)) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }

            return $this->redirectSent($params['app'], $noRoom);
        }

        try {
            FeedbackReport::create([
                'kind' => $validator->validated()['kind'], 'status' => 'new', 'app' => $params['app'], 'page' => $params['from'],
                'app_version' => $params['v'], 'message' => $message, 'user_id' => $user?->id, 'username' => $user?->username,
                'ip_address' => $request->ip(), 'user_agent' => mb_substr(mb_scrub((string) $request->userAgent(), 'UTF-8'), 0, 300) ?: null,
                'viewport' => Feedback::viewport($request->input('viewport')), 'locale' => app()->getLocale(), 'screenshot_path' => $path,
            ]);
        } catch (\Throwable $e) {
            // The picture goes with the failed save, and the log gets no word of the report (a database error repeats the text,
            // the address and the name in its message).
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            Cache::forget($dup);
            Log::warning('A feedback report could not be saved', ['error' => get_class($e), 'code' => (string) $e->getCode()]);

            return $back(['limit' => __('Your report could not be saved. Please try again.')]);
        }

        return $this->redirectSent($params['app'], $noRoom);
    }

    /** GET /feedback/sent: the thank-you page. Reloading it only shows it again. */
    public function sent(Request $request): RedirectResponse|View
    {
        $this->ensureOn();
        $state = $request->session()->get('feedback.sent');
        if (! is_array($state)) {
            return redirect()->route('feedback.create');
        }
        $slug = Feedback::app($state['app'] ?? null);

        return view('feedback.sent', ['app' => $slug, 'name' => Feedback::NAMES[$slug], 'back' => $this->backUrl($slug), 'noRoom' => (bool) ($state['no_room'] ?? false)]);
    }

    /** On the public host the form lives on the hub host, where the sign-in session is: send the visitor there. */
    public function toHub(Request $request): RedirectResponse
    {
        $this->ensureOn();
        $query = $request->getQueryString();

        return redirect()->to(hub_url($request->path()).($query ? '?'.$query : ''));
    }

    // ---- helpers ----------------------------------------------------------

    private function redirectSent(string $app, bool $noRoom): RedirectResponse
    {
        session()->put('feedback.sent', ['app' => $app, 'no_room' => $noRoom]);

        return redirect()->route('feedback.sent');
    }

    /** The part of an address that counts as one visitor: the whole of an IPv4 address, the first 64 bits of an IPv6 one. */
    public static function addressKey(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $packed = inet_pton($ip);

            return $packed === false ? $ip : bin2hex(substr($packed, 0, 8)).'/64';
        }

        return $ip;
    }

    private function ensureOn(): void
    {
        abort_unless(Feedback::active(), 404);
    }

    /** @return array{app: string, from: ?string, v: ?string} */
    private function params(array $input): array
    {
        return ['app' => Feedback::app($input['app'] ?? null), 'from' => Feedback::page($input['from'] ?? null), 'v' => Feedback::version($input['v'] ?? null)];
    }

    /** @param array{app: string, from: ?string, v: ?string} $params */
    private function form(Request $request, array $params, array $errors = []): View
    {
        $view = view('feedback.create', $this->viewData($request, $params, []));

        return $errors ? $view->withErrors($errors) : $view;
    }

    /**
     * @param  array{app: string, from: ?string, v: ?string}  $params
     * @param  array<string, mixed>  $input  what the visitor typed, when the form is answered at once instead of redirected
     * @return array<string, mixed>
     */
    private function viewData(Request $request, array $params, array $input): array
    {
        $agent = app(UserAgentService::class)->parse((string) $request->userAgent());

        return $params + [
            'input' => $input, 'ip' => $request->ip(), 'member' => $request->user(),
            'browser' => collect([$agent['browser'] ?? null, $agent['os'] ?? null])->reject(fn ($p) => ! $p || $p === 'Unknown')->implode(' · ') ?: null,
            'turnstileKey' => config('services.turnstile.site_key'),
            'query' => array_filter(['app' => $params['app'], 'from' => $params['from'], 'v' => $params['v']]),
        ];
    }

    private function imageMessage(string $reason): string
    {
        return match ($reason) {
            'type' => __('Use a PNG, JPEG or WebP image.'),
            'too_large' => __('That image is too large. Use one up to 5 MB.'),
            'busy' => __('The server is busy with another picture. Please try again in a moment.'),
            default => __('That file could not be read as an image.'),
        };
    }

    /** @param array<string, string> $messages */
    private function bag(array $messages): ViewErrorBag
    {
        return (new ViewErrorBag)->put('default', new MessageBag($messages));
    }

    /** Where "Back to the app" goes: the registered app's address, or the hub's own home. */
    private function backUrl(string $slug): ?string
    {
        if ($slug === 'hub') {
            return auth()->check() ? route('dashboard') : route('home');
        }
        $app = HopApps::find($slug);

        return $app ? rtrim($app->url, '/').'/' : null;
    }
}
