<?php

namespace App\Http\Middleware;

use Closure;
use App\Facility;
use App\Helpers\AuthHelper;
use App\User;
use Illuminate\Contracts\Auth\Factory as Auth;

class SemiPrivateCORS
{
    private $auth;

    public function __construct(Auth $auth) {
        $this->auth = $auth;
    }
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
        if (env('APP_ENV', 'prod') === "dev") {
            return $next($request);
        }

        if(in_array($_SERVER['REQUEST_METHOD'], ["GET","PUT","DELETE","POST"])) {
            // First try and authenticate
            $guards = ['jwt','web'];
            foreach($guards as $guard) {
                if ($this->auth->guard($guard)->check()) {
                    $this->auth->shouldUse($guard);
                }
            }

            // Now check it, or require apikey
            if (!\Auth::check()) {
                if ($request->has("apikey") && AuthHelper::validApiKeyv2($request->input("apikey"))) {
                    return $next($request);
                } elseif ($request->has("apikey")) {
                    return response()->json(generate_error("Invalid API Key"), 400);
                }
            } else {
                return $next($request)
                    ->header("Access-Control-Allow-Credentials", "true")
                    ->header("Access-Control-Allow-Headers", "x-csrf-token")
                    ->header("Access-Control-Allow-Methods", $_SERVER['REQUEST_METHOD'])
                    ->header("Access-Control-Allow-Origin", $_SERVER['HTTP_ORIGIN']);
            }

            return response()->json(generate_error("Unauthorized", true), 401);
        }
    }
}
