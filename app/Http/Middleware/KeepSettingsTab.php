<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A form of one Settings tab (API tokens, two-factor) answers with a redirect back to /settings. The browser drops
 * the #fragment from the Referer, so without a hint the page opens on its first tab (Account) and the person loses
 * the result. This puts `active_tab` into the flash of every redirect from such a form (done, refused, or a mistake
 * caught by validation) unless the controller chose one itself during this request. Answers for scripts (JSON) are left alone.
 */
class KeepSettingsTab
{
    public function handle(Request $request, Closure $next, string $tab): Response
    {
        $response = $next($request);
        if ($response instanceof RedirectResponse && ! $request->expectsJson() && ! in_array('active_tab', $request->session()->get('_flash.new', []), true)) {
            $request->session()->flash('active_tab', $tab);
        }

        return $response;
    }
}
