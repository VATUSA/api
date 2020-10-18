<?php

namespace App\Helpers;

use App\User;
use App\Facility;

class CERTHelper
{
    private static $baseUrl = "http://cert.vatsim.net/vatsimnet/admin/";

    public static function changeRating($cid, $newRating, $addToDatabase = false)
    {
        if (env('APP_ENV', 'dev') != "prod") {
            if ($addToDatabase) {
                $user = User::find($cid);
                $user->rating = $newRating;
                $user->save();

                log_action($cid,
                    "Rating set to " . RatingHelper::intToShort($newRating) . " by " . \Auth::user()->fullname() . " (" . \Auth::user()->cid . ")");
            }

            # Don't do anything
            return 1;
        }

        if (!is_numeric($newRating)) {
            $newRating = RatingHelper::shortToInt($newRating);
        }

        $url = static::buildUrl("ratch.php", ["id" => $cid, "rat" => $newRating]);
        $result = file_get_contents($url);
        if ($result == "ERR:NoExist" || $result == "ERR:NotAllowed") {
            \Log::fatal("Got $result when trying to change rating for $cid to $newRating, submitted by " . \Auth::user()->cid);

            return 0;
        }

        if ($addToDatabase) {
            $user = User::find($cid);
            $user->rating = $newRating;
            $user->save();

            log_action($cid,
                "Rating set to " . RatingHelper::intToShort($newRating) . " by " . \Auth::user()->fullname() . " (" . \Auth::user()->cid . ")");
        }

        return 1;
    }

    private static function buildUrl($url, $args = array())
    {
        $queryString = "";
        foreach ($args as $key => $value) {
            if ($queryString != "") {
                $queryString .= "&";
            }
            $queryString .= "$key=$value";
        }
        if ($queryString) {
            $queryString .= "&";
        }
        $queryString .= "authid=" . env('CERT_ID') . "&authpassword=" . env('CERT_PASSWORD');

        return static::$baseUrl . $url . "?" . $queryString;
    }
}