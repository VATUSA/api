<?php

namespace App\Http\Controllers\Login;

use App\Classes\SMFHelper;
use App\Helpers\EmailHelper;
use App\Helpers\ULSHelper;
use App\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Classes\OAuth\SSO;
use Carbon\Carbon;

/**
 * Class SSOController
 * @package App\Http\Controllers\Login
 */
class SSOController extends Controller
{
    /**
     * @var SSO
     */
    private $sso;

    /**
     * SSOController constructor.
     */
    public function __construct() {
        $this->sso = new SSO(config('sso.base'),
                             config('sso.key'),
                             config('sso.secret'),
                             config('sso.method'),
                             config('sso.cert'),
                             ['allow_suspended' => false, 'allow_inactive' => false]
                            );
    }

    /**
     * @param Request $request
     *
     * @return bool|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector|mixed|void
     * @throws \App\Classes\OAuth\SSOException
     */
    public function getIndex(Request $request) {
        if (env('APP_ENV', 'prod') != 'dev') {
          //require_once(config('sso.forumapi',''));
        }
        if ($request->has("logout")) {
            if (env('APP_ENV', 'prod') != 'dev') {
              //smfapi_logout();
            }
            \Auth::logout();
            if (isset($_SERVER['HTTP_REFERER'])) {
                $return = $_SERVER['HTTP_REFERER'];
            } else {
                $return = 'https://www.vatusa.net';
            }

            return redirect("https://forums.vatusa.net/api.php?logout=1&return=$return");
            return;
        }

        /* Lots to check here ... but this is our multi-point redirect */
        if ($request->has('home')) {
            $request->session()->put('return', env('SSO_RETURN_HOME'));
        } elseif ($request->has('agreed')) {
            $request->session()->put('return', env('SSO_RETURN_AGREED'));
            $request->session()->put('fromAgreed', true);
        } elseif($request->has('homedev')) {
            $request->session()->put('return', env('SSO_RETURN_HOMEDEV'));
        } elseif ($request->has('forums')) {
            $request->session()->put('return', env('SSO_RETURN_FORUMS'));
        } elseif ($request->has('localdev')) {
            $request->session()->put('return', env('SSO_RETURN_LOCALDEV'));
        } elseif ($request->has('uls')) {
            $request->session()->put('return', env('SSO_RETURN_ULS'));
        } elseif ($request->has("ulsv2")) {
            $request->session()->put("return", env("SSO_RETURN_ULSv2"));
        } elseif ($request->has('exam')) {
            $request->session()->put('return', env('SSO_RETURN_EXAM', 'https://www.vatusa.net/exam/0'));
        } else {
            $request->session()->put('return', env('SSO_RETURN_FORUMS'));
        }

        /* If already logged in, don't send to SSO */
        if (\Auth::check()) {
            $return = $request->session()->get("return");
            $request->session()->forget("return");
            return ULSHelper::doHandleLogin(\Auth::user()->cid, $return);
        }

        return $this->sso->login(
            config('sso.return'),
            function($key, $secret, $url) use ($request) {
                $request->session()->put("SSO", ['key' => $key, 'secret' => $secret]);
                $request->session()->save();        // THIS *SHOULDN'T BE NEEDED!!!  But ... it is.
                return redirect($url);
            }
        );
    }

    public function getReturn(Request $request) {
        if (isset($_REQUEST['cancel'])) {
            $request->session()->forget("SSO");
            $request->session()->forget("return");
            echo "Login request cancelled."; exit;
        }
        $sso = $request->session()->get("SSO");
        if (!isset($sso['key']) || !$sso['key'] ||
            !isset($sso['secret']) || !$sso['secret']) {
            return response("Your client didn't return the proper session cookie.  You may need to close your browser and try again.", 401);
        }

        if (!$request->input('oauth_verifier')) {
            return response("Missing CERT identification.  Cannot continue.", 401);
        }

        return $this->sso->validate(
            $sso['key'],
            $sso['secret'],
            $request->input('oauth_verifier'),
            function($user, $request) {
                session()->forget("SSO");
                $return = session("return", env("SSO_RETURN_FORUMS"));
                session()->forget("return");

                // Check if user is registered in forums...
                if (SMFHelper::isRegistered($user->id)) {
                    SMFHelper::updateData($user->id, $user->name_last, $user->name_first, $user->email);
                    SMFHelper::setPermissions($user->id);
                } else {
                    $regOptions = [
                        'member_name' => $user->id,
                        'real_name' => $user->name_first . " " . $user->name_last,
                        'email' => $user->email,
                        'send_welcome_email' => false,
                        'require' => 'nothing'
                    ];
                    $token = ULSHelper::base64url_encode(json_encode($regOptions));
                    $signature = hash_hmac("sha512", $token, base64_decode(env("FORUM_SECRET")));
                    $signature = ULSHelper::base64url_encode($signature);
                    $data = file_get_contents("https://forums.vatusa.net/api.php?register=1&data=$token&signature=$signature");
                    if ($data != "OK") {
                        throw new \Exception("Failed to create forum user $data");
                    }
                }
                $member = User::find($user->id);
                if (!$member) {
                    $member = new User();
                    $member->cid = $user->id;
                    $member->email = $user->email;
                    $member->fname = $user->name_first;
                    $member->lname = $user->name_last;
                    $member->rating = $user->rating->id;
                    $member->facility = (($user->division->code == "USA") ? "ZAE" : "ZZN");
                    $member->facility_join = Carbon::now();
                    $member->flag_needbasic = 1;
                    $member->flag_xferOverride = 0;
                    $member->flag_homecontroller = (($user->division->code == "USA") ? 1 : 0);
                    $member->save();

                    if ($member->flag_homecontroller) {
                        EmailHelper::sendEmail($member->email, "Welcome to VATUSA", "emails.user.join", []);
                    }
                }

                return ULSHelper::doHandleLogin($user->id, $return);
            }
        );
    }
}
