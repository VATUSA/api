<?php

namespace App\Http\Middleware;

use Closure;

class Subdomain
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $route = $request->route();
        $domain = $route->parameter("_domain");
        $tld = $route->parameter("_tld");
        $route->forgetParameter("_domain");
        $route->forgetParameter("_tld");
        return $next($request);
    }
}
