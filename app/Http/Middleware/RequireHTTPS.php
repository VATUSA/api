<?php

namespace App\Http\Middleware;

use Closure;
use App\Exceptions\NotSecuredException;

/**
 * Class RequireHTTPS
 * @package App\Http\Middleware
 */
class RequireHTTPS
{

    /**
     * Check if connection is secure, don't even attempt or redirect.. just break it.
     *
     * @param $request
     * @param Closure $next
     * @return mixed
     * @throws NotSecuredException
     */
    public function handle($request, Closure $next)
    {
        if (!$request->secure()) {
            throw new NotSecuredException("Connection not secured");
        }

        return $next($request);
    }
}
