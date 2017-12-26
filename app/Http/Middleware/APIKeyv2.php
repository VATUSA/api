<?php

namespace App\Http\Middleware;

use Closure;
use App\Facility;
use App\Helpers\AuthHelper;
use App\User;

class APIKeyv2
{
    /**
     * Handle an incoming request.
     *
     * Same as Public, APIKey will handle
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next) {
        $ip = isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : "";
        $apikey = $request->get('apikey', null);

        if ($apikey != null) {
            if (!AuthHelper::validApiKeyv2($apikey)) {
                return response()->json(generate_error("Bad request"), 400);
            }
        }

        return $next($request);
    }
}
