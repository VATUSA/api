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
                if ($request->has("apikey") && AuthHelper::validApiKey($_SERVER['REMOTE_ADDR'], $request->input("apikey"))) {
                    return $next($request);
                }
            } else {
                return $next($request);
            }

            return response()->json(generate_error("Forbidden", true), 401);
        }
    }
}
