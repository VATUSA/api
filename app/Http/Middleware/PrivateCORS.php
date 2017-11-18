<?php
namespace App\Http\Middleware;
use Closure;
class PrivateCORS
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
        if(in_array($_SERVER['REQUEST_METHOD'], ["GET","PUT","DELETE","POST"])) {
            if (!isset($_SERVER['origin']) || preg_match("/^http(s?):\/\/[^/]+\.vatusa\.net/", $_SERVER['origin'])) {
                abort(400, "Malformed origin");
            }
        }
        return $next($request);
    }
}
