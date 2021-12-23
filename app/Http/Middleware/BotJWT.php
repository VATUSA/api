<?php

namespace App\Http\Middleware;

use Closure;
use Exception;
use Firebase\JWT\JWT;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class BotJWT
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure                 $next
     *
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $token = $request->bearerToken();
        if (!$token) {
            abort(401, 'No token provided');
        }

        JWT::$leeway = 60;
        try {
            JWT::decode($token, config('services.discord.botSecret'), ['HS512']);
        } catch (Exception $e) {
            abort(403, 'Invalid token');
        }

        return $next($request);
    }
}
