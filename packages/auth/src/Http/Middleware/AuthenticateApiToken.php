<?php

namespace Hop\Auth\Http\Middleware;

use Closure;
use Hop\Auth\Models\ApiToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * `hop.token[:scope]`: Bearer tokens from the shared api_tokens table, with
 * expiry, suspended-account and per-token hourly rate limit checks, and an
 * optional required scope (e.g. hop.token:link:create).
 */
class AuthenticateApiToken
{
    /** Length of the rate limit window, in seconds. */
    private const WINDOW = 3600;

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
        // Count first, then compare: the hit is atomic, so parallel requests cannot all slip under the limit.
        $limit = $this->limitFor($token);
        $key = 'hop:api-rate:'.$token->id;
        if (RateLimiter::hit($key, self::WINDOW) > $limit) {
            $retryAfter = max(1, RateLimiter::availableIn($key));

            return $this->fail(429, 'Rate Limit Exceeded', "Rate limit of {$limit} requests per hour exceeded", ['retry_after' => $retryAfter])
                ->header('Retry-After', (string) $retryAfter);
        }
        $token->markAsUsed();
        $request->attributes->set('api_user', $user);
        $request->attributes->set('api_token', $token);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }

    /** The token's own hourly limit; a missing or non-positive value falls back to the configured default. */
    private function limitFor(ApiToken $token): int
    {
        $limit = (int) $token->rate_limit;

        return $limit > 0 ? $limit : max(1, (int) config('hop-auth.api_rate_limit', 100));
    }

    private function fail(int $status, string $error, string $message, array $extra = []): JsonResponse
    {
        return response()->json(['success' => false, 'error' => $error, 'message' => $message] + $extra, $status);
    }
}
