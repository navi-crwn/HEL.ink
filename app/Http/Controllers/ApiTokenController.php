<?php

namespace App\Http\Controllers;

use App\Models\ApiToken;
use App\Support\HopApp;
use App\Support\HopApps;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * API tokens (hlk_…) live in the hub and work at the apps that check the shared `api_tokens` table
 * through hop/auth (config `hop.api_docs` without `own_keys`: LinkHop), limited to the scopes chosen
 * here. An app with `own_keys` (PixelHop) issues its own keys and refuses hub tokens, so its scopes
 * are not offered. Settings and the API page read their facts from here, so they cannot contradict
 * each other.
 */
class ApiTokenController extends Controller
{
    /** Requests per hour a new token may make at each app (shown on the API page). */
    public const RATE_LIMIT = 100;

    /**
     * The apps whose API accepts hub tokens: those with a token API that do not issue their own keys.
     *
     * @param  iterable<HopApp>  $apps
     * @return list<HopApp>
     */
    public static function hubTokenApps(iterable $apps): array
    {
        $out = [];
        foreach ($apps as $app) {
            $api = config('hop.api_docs.'.$app->slug);
            if ($api && empty($api['own_keys'])) {
                $out[] = $app;
            }
        }

        return $out;
    }

    /**
     * The apps that issue their own keys, with the address of the page where each is made.
     *
     * @param  iterable<HopApp>  $apps
     * @return list<HopApp>
     */
    public static function ownKeyApps(iterable $apps): array
    {
        $out = [];
        foreach ($apps as $app) {
            if (config('hop.api_docs.'.$app->slug.'.own_keys')) {
                $out[] = $app;
            }
        }

        return $out;
    }

    /**
     * The scopes a person can pick: those of apps that have a token API. A scope whose app
     * (config `hop.api_docs.<slug>.scopes`, the prefix before ":") has none is not offered.
     *
     * @param  list<string>  $appSlugs  apps whose API accepts hub tokens
     * @return array<string, string> scope => what it allows
     */
    public static function availableScopes(array $appSlugs): array
    {
        $prefixes = [];
        foreach ($appSlugs as $slug) {
            if ($prefix = config("hop.api_docs.{$slug}.scopes")) {
                $prefixes[] = (string) $prefix;
            }
        }

        return array_filter(ApiToken::SCOPES, fn ($what, $scope) => in_array(Str::before($scope, ':'), $prefixes, true), ARRAY_FILTER_USE_BOTH);
    }

    /** @return list<string> the scopes (ApiToken::SCOPES) of apps that issue their own keys */
    public static function ownKeyScopes(): array
    {
        $prefixes = [];
        foreach (self::ownKeyApps(HopApps::all()) as $app) {
            $prefixes[] = (string) config("hop.api_docs.{$app->slug}.scopes");
        }

        return array_values(array_filter(array_keys(ApiToken::SCOPES), fn ($scope) => in_array(Str::before($scope, ':'), $prefixes, true)));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'scopes' => 'nullable|array',
            // The scopes of an app that issues its own keys are not accepted for a new token (a form left open from before is refused).
            'scopes.*' => ['string', Rule::in(array_keys(ApiToken::SCOPES)), Rule::notIn(self::ownKeyScopes())],
        ]);
        $scopes = array_values((array) $request->input('scopes', [])) ?: null;
        $result = ApiToken::generate(
            userId: $request->user()->id,
            name: $request->input('name'),
            rateLimit: self::RATE_LIMIT,
            scopes: $scopes,
        );
        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'token' => $result['plain_token'],
                'name' => $result['model']->name,
                'scopes' => $result['model']->scopes,
                'message' => __('Token created. Copy it now: it is shown only this once.'),
            ]);
        }

        return back()->with('status', __('Token created.'))->with('api_token', $result['plain_token']);
    }

    public function destroy(ApiToken $apiToken)
    {
        if ($apiToken->user_id !== auth()->id()) {
            abort(403, 'Unauthorized');
        }
        $apiToken->delete();
        if (request()->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('Token revoked.')]);
        }

        return back()->with('status', __('Token revoked.'));
    }

    public function docs()
    {
        return view('api-docs');
    }
}
