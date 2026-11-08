<?php

namespace App\Http\Controllers;

use App\Models\ApiToken;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * API tokens live in the hub and work at every app that has a bearer-token API
 * (config `hop.api_docs`: LinkHop, PixelHop) through hop/auth, limited to the
 * scopes chosen here. Settings and the API page read their facts from here, so
 * they cannot contradict each other.
 */
class ApiTokenController extends Controller
{
    /** Requests per hour a new token may make at each app (shown on the API page). */
    public const RATE_LIMIT = 100;

    /**
     * The scopes a person can pick: those of apps that have a token API. A scope whose app
     * (config `hop.api_docs.<slug>.scopes`, the prefix before ":") has none is not offered.
     *
     * @param  list<string>  $appSlugs  apps with a token API
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

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'scopes' => 'nullable|array',
            'scopes.*' => 'string|in:'.implode(',', array_keys(ApiToken::SCOPES)),
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
