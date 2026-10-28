<?php

namespace App\Http\Controllers;

use App\Models\ApiToken;
use Illuminate\Http\Request;

/**
 * API tokens live in the hub and work at every app (link.hel.ink/api,
 * bio.hel.ink/api) through hop/auth, limited to the scopes chosen here.
 */
class ApiTokenController extends Controller
{
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
            rateLimit: 100,
            scopes: $scopes,
        );
        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'token' => $result['plain_token'],
                'name' => $result['model']->name,
                'scopes' => $result['model']->scopes,
                'message' => 'API token created successfully. Save this token - you won\'t be able to see it again!',
            ]);
        }

        return back()->with('status', 'API token created!')->with('api_token', $result['plain_token']);
    }

    public function destroy(ApiToken $apiToken)
    {
        if ($apiToken->user_id !== auth()->id()) {
            abort(403, 'Unauthorized');
        }
        $apiToken->delete();
        if (request()->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'API token revoked successfully']);
        }

        return back()->with('status', 'API token revoked successfully');
    }

    public function docs()
    {
        return view('api-docs');
    }
}
