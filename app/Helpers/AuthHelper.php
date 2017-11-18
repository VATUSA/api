<?php
namespace App\Helpers;

use App\User;
use App\Facility;

class AuthHelper {
    public static function validApiKey($ip, $key, $fac = null) {
        $facility = Facility::where("apikey", $key)->where("ip", $ip)->first();
        if ($facility || ($fac && $fac === $facility->id)) {
            return true;
        }
        $facility = Facility::where("api_sandbox_key", $key)->where("api_sandbox_ip", $ip)->first();
        if ($facility || ($fac && $fac === $facility->id)) {
            return true;
        }

        return false;
    }

    public static function isSandboxKey($key, $fac = null) {
        $facility = Facility::where("api_sandbox_key", $key)->first();
        if ($facility || ($fac && $fac === $facility->id)) {
            return true;
        }

        return false;
    }
}
