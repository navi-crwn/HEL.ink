<?php

namespace Hop\Auth;

use Hop\Auth\Http\Middleware\AuthenticateApiToken;
use Hop\Auth\Http\Middleware\AuthenticateViaHub;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

/**
 * hop/auth: HOP ID for the consumer apps (LinkHop, BioHop, …): SSO start,
 * callback and logout routes, the `hop.auth` middleware, and `hop.token`
 * for API tokens from the shared table. The hub itself only uses the
 * ApiToken model and the middleware aliases.
 */
class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/hop-auth.php', 'hop-auth');
    }

    public function boot(Router $router): void
    {
        $this->publishes([__DIR__.'/../config/hop-auth.php' => config_path('hop-auth.php')], 'hop-auth-config');
        $router->aliasMiddleware('hop.auth', AuthenticateViaHub::class);
        $router->aliasMiddleware('hop.token', AuthenticateApiToken::class);
        // The hub issues tickets; it never consumes them.
        if (config('hop.current', config('hop-auth.app')) !== 'hub' && config('hop-auth.routes.start')) {
            $this->loadRoutesFrom(__DIR__.'/../routes/sso.php');
        }
    }
}
