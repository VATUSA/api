<?php
namespace App\Http\Middleware;

use Closure;
use App\Facility;

class APIKey
{
    public function handle($request, Closure $next)
    {


        return $next($request);
    }
}