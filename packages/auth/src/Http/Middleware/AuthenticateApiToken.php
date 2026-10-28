<?php

namespace Hop\Auth\Http\Middleware;

use Closure;
use Hop\Auth\Models\ApiToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * `hop.token[:scope]`: Bearer tokens from the shared api_tokens table, with
 * expiry, suspended-account and per-token hourly rate limit checks, and an
 * optional required scope (e.g. hop.token:link:create).
 */
class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next, ?string $scope = null): Response
    {
        $header = (string) $request->header('Authorization', '');
        if (! str_starts_with($header, 'Bearer ')) {
            return $this->fail(401, 'Unauthorized', 'Missing or invalid Authorization header. Use: Authorization: Bearer YOUR_TOKEN');
        }
        $token = ApiToken::findByPlainToken(substr($header, 7));
        if (! $token) {
            return $this->fail(401, 'Unauthorized', 'Invalid API token');
        }
        if (! $token->isValid()) {
            return $this->fail(401, 'Unauthorized', 'API token has expired');
        }
        $user = $token->user;
        if (! $user || (method_exists($user, 'isSuspended') ? $user->isSuspended() : ($user->status ?? 'active') === 'suspended')) {
            return $this->fail(403, 'Forbidden', 'Account suspended');
        }
        if ($scope && ! $token->can($scope)) {
            return $this->fail(403, 'Forbidden', "This token does not have the {$scope} scope");
        }
        $key = 'api_rate_limit:'.$token->id;
        $count = (int) Cache::get($key, 0);
        if ($count >= $token->rate_limit) {
            return $this->fail(429, 'Rate Limit Exceeded', "Rate limit of {$token->rate_limit} requests per hour exceeded", ['retry_after' => 3600]);
        }
        $count === 0 ? Cache::put($key, 1, now()->addHour()) : Cache::increment($key);
        $token->markAsUsed();
        $request->attributes->set('api_user', $user);
        $request->attributes->set('api_token', $token);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }

    private function fail(int $status, string $error, string $message, array $extra = []): Response
    {
        return response()->json(['success' => false, 'error' => $error, 'message' => $message] + $extra, $status);
    }
}
