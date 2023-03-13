<?php
namespace App\Http\Middleware;
use Closure;
class PublicCORS
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
        return $next($request)
            ->header("Access-Control-Allow-Credentials", "true")
            ->header("Access-Control-Allow-Headers", "x-csrf-token")
            ->header("Access-Control-Allow-Methods", '*')
            ->header("Access-Control-Allow-Origin", $_SERVER['HTTP_ORIGIN'] ?? $request->url() . ', *');
    }
}
