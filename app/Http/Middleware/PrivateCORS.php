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
        if (env('APP_ENV', 'prod') === "dev") {
            return $next($request);
        }

        if(in_array($_SERVER['REQUEST_METHOD'], ["GET","PUT","DELETE","POST"])) {
            if (!isset($_SERVER['HTTP_ORIGIN']) || !preg_match("~^(http|https)://[^/]+\.vatusa\.net~i", $_SERVER['HTTP_ORIGIN'])) {
                abort(400, "Malformed origin " . $_SERVER['HTTP_ORIGIN']);
            }
        }
        return $next($request);
    }
}
