<?php

namespace App\Helpers;

use App\Classes\VATUSAMoodle;
use App\Facility;
use App\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;

class ULSHelper
{
    public static function generateToken($facility = ""): string
    {
        if (!isset($_SERVER['REMOTE_ADDR']) || $_SERVER['REMOTE_ADDR'] == "") {
            header('HTTP/1.1 500 Internal Server Error');
            echo 'REMOTE_ADDR not defined.';
            exit;
        }


        $fac = Facility::find($facility);
        if (!$fac->uls_secret) {
            header('HTTP/1.1 500 Internal Server Error');
            echo 'Secret not available for defined facility.';
            exit;
        }
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        $tokenRaw = $fac->uls_secret . "-" . $ip;
        $token = sha1($tokenRaw);

        return $token;
    }

    public static function generatev2Token(User $user, Facility $facility, bool $rfc7519): ?array
    {
        $data = [
            'iss' => 'VATUSA',
            'aud' => $facility->id,
            'sub' => $rfc7519 ? strval($user->cid) : $user->cid,
            'ip'  => $_SERVER['REMOTE_ADDR'] ?? 'not available',
            'iat' => time(),
            'nbf' => time(),
            'exp' => time() + 20
        ];

        return static::generatev2Signature($data);
    }

    public static function generatev2Signature(array $data): ?array
    {
        if ($data === null) {
            return null;
        }
        $signature = hash_hmac('sha256', encode_json($data), env('ULS_SECRET'));
        $data['sig'] = $signature;

        return $data;
    }

    public static function doHandleLogin($cid, $return, $isTest = false)
    {
        //require_once(config('sso.forumapi', ''));
        //smfapi_login($cid, 14400);
        \Auth::loginUsingId($cid, true);

        if (in_array(app()->environment(), ["prod", "staging"])) {
            //Sync Moodle Roles
            $moodle = new VATUSAMoodle(false, $isTest);
            if ($id = $moodle->getUserId($cid)) {
                //Update Information
                $moodle->updateUser(Auth::user(), $id);
            } else {
                //Create User
                $moodle->createUser(Auth::user());
            }
            Artisan::queue("moodle:sync", ["user" => $cid]);

            $moodle->setSSO(true);
            $response = $moodle->request("auth_userkey_request_login_url",
                ['user' => ['idnumber' => Auth::user()->cid]]);
            $url = $response["loginurl"];
        }

        if (!in_array(app()->environment(), ["dev", "local"])) {
            $token = [
                "cid"    => (string)$cid,
                "nlt"    => time() + 7,
                "return" => $return
            ];
            $token = static::base64url_encode(json_encode($token));
            $signature = static::base64url_encode(hash_hmac("sha512", $token, base64_decode(env("FORUM_SECRET"))));

            if (str_contains(config('app.url'), "staging")) {
                $forumsUrl = str_replace("staging", "forums.staging", config('app.url'));
            } else {
                $forumsUrl = str_replace("api", "forums", config('app.url'));
            }

            return redirect($url . "&wantsurl=" . urlencode("$forumsUrl/api.php?login=1&token=$token&signature=$signature"));
        }

        return redirect(env('SSO_RETURN_HOME'));
    }

    public static function base64url_encode($data, $use_padding = false): string
    {
        $encoded = strtr(base64_encode($data), '+/', '-_');

        return true === $use_padding ? $encoded : rtrim($encoded, '=');
    }

    public static function base64url_decode($data)
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
