<?php

namespace App\Helpers;

use App\User;
use App\Facility;

class AuthHelper
{
    public static function validApiKey($ip, $key, $fac = null)
    {
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

    public static function validApiKeyv2($key, $fac = null)
    {
        if (is_null($key)) {
            return false;
        }
        $fac = strtoupper($fac);

        $facility = Facility::where("apikey", $key)
            ->orWhere("api_sandbox_key", $key)->first();
        if ((!$fac && $facility) || ($fac && $facility && $fac === $facility->id)) {
            return true;
        }
        /** Inter-ARTCC Visiting Agreements - Allow Data Retrieval */
        $exceptions = [
            //Exception 1: ZTL-ZHU-ZJX
            'ZTL' => ['ZHU', 'ZJX'],
            'ZHU' => ['ZTL', 'ZJX'],
            'ZJX' => ['ZHU', 'ZTL']
        ];

        if ($fac && $facility && in_array($fac, $exceptions[$facility->id] ?? [])) {
            return true;
        }

        return false;
    }

    public static function isSandboxKey($key, $fac = null)
    {
        $facility = Facility::where("api_sandbox_key", $key)->first();
        if ((!$fac && $facility) || ($fac && $facility && $fac === $facility->id)) {
            return true;
        }

        return false;
    }
}
