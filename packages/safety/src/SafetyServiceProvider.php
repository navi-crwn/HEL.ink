<?php

namespace Hop\Safety;

use Hop\Safety\Console\UpdateThreatFeeds;
use Illuminate\Support\ServiceProvider;

/**
 * hop/safety: the one set of URL and content safety rules for every HOP app.
 * LinkSafety scores a URL (patterns, risky TLDs, the shared domain_blacklist
 * table, threat feeds, optional Safe Browsing); UrlGuard blocks private and
 * local targets; SafeContent sanitises HTML and text. The domain_blacklist
 * table is LinkHop's; the other apps only read it.
 */
class SafetyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/safety.php', 'safety');
        // One instance per request/job so the domain blacklist is loaded once.
        $this->app->scoped(LinkSafety::class);
    }

    public function boot(): void
    {
        $this->publishes([__DIR__.'/../config/safety.php' => config_path('safety.php')], 'hop-safety-config');
        if ($this->app->runningInConsole()) {
            $this->commands([UpdateThreatFeeds::class]);
        }
    }
}
