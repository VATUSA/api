<?php

namespace App\Http\Middleware;

use Closure;
use App\Facility;
use App\Helpers\AuthHelper;
use App\User;

class APIKey
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
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        $apikey = $request->apikey;

        if ($apikey != "vatusa") {
            if (Facility::where('apikey', $apikey)->where('ip', $ip)->count() < 1 &&
                Facility::where('api_sandbox_key', $apikey)->where('api_sandbox_ip', $ip)->count() < 1) {
                \Log::warning("API Unauthorized request from $apikey and $ip");
                return response()->json(generate_error("Unauthorized", true), 401);
            }

            if (Facility::where('api_sandbox_key', $apikey)->where('api_sandbox_ip', $ip)->count() >= 1) {
                // Sandbox, force test flag..
                $request->merge(['test' => 1]);
                $facility = Facility::where('api_sandbox_key', $apikey)->where('api_sandbox_ip', $ip)->first();
            } else {
                $facility = Facility::where('apikey', $apikey)->where('ip', $ip)->first();
            }
            $data = file_get_contents("php://input");
            $data .= var_export($_POST, true);

            \DB::table("api_log")->insert(
                ['facility' => $facility->id,
                    'datetime' => \DB::raw('NOW()'),
                    'method' => $request->method(),
                    'url' => $request->fullUrl(),
                    'data' => ($request->has('test') ? "SANDBOX: " : "LIVE: ") . $data]);
        } else {
            $user = AuthHelper::getAuthUser();
            if (!($user instanceof User)) {
                return response()->json(generate_error("Unauthorized", true), 401);
            }
        }

        return $next($request);
    }
}
