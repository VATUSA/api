<?php

namespace App\Helpers;

use App\Rating;
use App\User;
use App\Facility;

class Helper
{
    public static function version()
    {
        $version = "VATUSA-";
        if (file_exists(base_path("gitversion"))) {
            $version .= file_get_contents(base_path('gitversion'));
        } else {
            $version .= "dev";
        }

        return $version;
    }

    public static function nameFromCID($cid, $retCID = 0)
    {
        $ud = User::where('cid', $cid)->count();
        if ($ud) {
            $u = User::where('cid', $cid)->first();

            return ($retCID ? $u->fname . ' ' . $u->lname . ' - ' . $u->cid : $u->fname . ' ' . $u->lname);
        } elseif ($cid == "0") {
            return "Automated";
        } else {
            return 'Unknown';
        }
    }

    public static function emailFromCID($cid)
    {
        $u = User::where('cid', $cid)->count();
        if ($u) {
            $u = User::where('cid', $cid)->first();

            return $u->email;
        } elseif ($cid == "0") {
            return "Automated";
        } else {
            return 'Unknown';
        }
    }

    public static function ratingIntFromShort($short)
    {
        return Rating::where('short', $short)->first()->id;
    }

    public static function ratingShortFromInt($rating)
    {
        return Rating::find($rating)->short;
    }

    public static function ratingLongFromInt($rating)
    {
        $rating = Rating::find($rating);

        return $rating->long;
    }

    public static function ratingLngSht($rat)
    {
        return Rating::where('long', $rat)->first()->short;
    }

    public static function ratingShtLng($rat)
    {
        return Rating::where('short', $rat)->first()->long;
    }

    public static function facShtLng($fac)
    {
        $facility = Facility::find($fac);

        return ($facility != null ? $facility->name : 'Unknown');
    }

    public static function testCORS()
    {
        if (env('APP_ENV', 'prod') === "prod") {
            if (in_array(
                $_SERVER['REQUEST_METHOD'], ["GET", "PUT", "DELETE", "POST"]
            )
            ) {
                if (!isset($_SERVER['HTTP_ORIGIN'])
                    || !preg_match(
                        "~^(http|https)://[^/]+\.vatusa\.net(:\d{2,4})?~i",
                        $_SERVER['HTTP_ORIGIN']
                    )
                ) {
                    return false;
                }
            }
        } else {
            if (in_array(
                $_SERVER['REQUEST_METHOD'], ["GET", "PUT", "DELETE", "POST"]
            )
            ) {
                if (!isset($_SERVER['HTTP_ORIGIN'])
                    || (
                    !preg_match(
                        "~^(http|https)://[^/]+\.vatusa\.(net|devel|cloud)(:\d{2,4})?~i",
                        $_SERVER['HTTP_ORIGIN'])
                    )
                ) {
                    return false;
                }
            }
        }

        return true;
    }
}