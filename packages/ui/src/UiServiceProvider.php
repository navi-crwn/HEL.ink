<?php

namespace Hop\Ui;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

/**
 * hop/ui: the cross-app navigation every HOP app shows, drawn from the one
 * list of apps in config/hop.php. Components (namespace `hop`):
 *
 *   <x-hop::pill />      the row of app pills (hub, LinkHop, BioHop, …), current one marked
 *   <x-hop::nav-items /> sidebar items for the apps, for an existing <ul>
 *   <x-hop::badge />     "a HOP app" wordlet with a link to the hub
 */
class UiServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'hop');
        Blade::componentNamespace('Hop\\Ui\\View\\Components', 'hop');
        Blade::anonymousComponentPath(__DIR__.'/../resources/views/components', 'hop');
        $this->publishes([__DIR__.'/../resources/views' => resource_path('views/vendor/hop')], 'hop-ui-views');
    }
}
