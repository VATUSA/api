<?php

namespace App\Helpers;

use App\Facility;
use App\Role;
use App\User;

class ULSHelper
{
    public static function generateToken($facility = "") {
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

        $tokenRaw = $fac->uls_secret . "-" . $_SERVER['REMOTE_ADDR'];
        $token = sha1($tokenRaw);
        return $token;
    }

    public static function generatev2Token(User $user, Facility $facility) {
        $data = [
            'iss' => 'VATUSA',
            'aud' => $facility->id,
            'sub' => $user->cid,
            'ip' => $_SERVER['REMOTE_ADDR'],
            'iat' => time(),
            'nbf' => time(),
            'exp' => time() + 20
        ];
        return static::generatev2Signature($data);
    }

    public static function generatev2Signature(array $data) {
        $signature = hash_hmac('sha256', encode_json($data), env('ULS_SECRET') );
        $data['sig'] = $signature;
        return $data;
    }

    public static function doHandleLogin($cid, $return) {
        require_once(config('sso.forumapi',''));
        smfapi_login($cid, 14400);
        \Auth::loginUsingId($cid);
        return redirect($return);
    }
}
