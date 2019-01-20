<?php

namespace App\Http\Middleware;

use Closure;

class PrivateCORS
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure                 $next
     *
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (env('APP_ENV', 'prod') === "prod") {
            if (in_array(
                $_SERVER['REQUEST_METHOD'], ["GET", "PUT", "DELETE", "POST"]
            )
            ) {
                if (!isset($_SERVER['HTTP_ORIGIN'])
                    || !preg_match(
                        "~^(http|https)://[^/]+\.vatusa\.net(:\d{2,4})?~i",
                        $_SERVER['HTTP_ORIGIN']
                    )
                ) {
                    abort(400, "Malformed origin");
                }
            }
        } else {
            if (in_array(
                $_SERVER['REQUEST_METHOD'], ["GET", "PUT", "DELETE", "POST"]
            )
            ) {
                if (!isset($_SERVER['HTTP_ORIGIN'])
                    || (
                        !preg_match(
                        "~^(http|https)://[^/]+\.vatusa\.net(:\d{2,4})?~i",
                        $_SERVER['HTTP_ORIGIN'])
                        && !preg_match(
                        "~^(http|https)://[^/]+\.vatusa\.devel(:\d{2,4})?~i",
                        $_SERVER['HTTP_ORIGIN'])
                    )
                ) {
                    abort(400, "Malformed origin");
                }
            }
        }

        return $next($request)
            ->header("Access-Control-Allow-Credentials", "true")
            ->header("Access-Control-Allow-Headers", "x-csrf-token")
            ->header("Access-Control-Allow-Methods", $_SERVER['REQUEST_METHOD'])
            ->header("Access-Control-Allow-Origin", $_SERVER['HTTP_ORIGIN']);
    }
}
