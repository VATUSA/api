<?php

namespace App\Http\Controllers\Login;

use App\Classes\SMFHelper;
use App\Helpers\EmailHelper;
use App\Helpers\ULSHelper;
use App\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Classes\OAuth\SSO;

require_once(config('sso.forumapi',''));

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
     */
    public function getIndex(Request $request) {
        if ($request->has("logout")) {
            smfapi_logout();
            \Auth::logout();
            if (isset($_SERVER['HTTP_REFERER'])) {
                header("Location: " . $_SERVER['HTTP_REFERER']);
            } else {
                header("Location: https://www.vatusa.net");
            }
            return;
        }

        /* Lots to check here ... but this is our multi-point redirect */
        if ($request->has('home')) {
            $request->session()->put('return', env('SSO_RETURN_HOME'));
        } elseif ($request->has('homedev')) {
            $request->session()->put('return', env('SSO_RETURN_HOMEDEV'));
        } elseif ($request->has('forums')) {
            $request->session()->put('return', env('SSO_RETURN_FORUMS'));
        } elseif ($request->has('localdev')) {
            $request->session()->put('return', env('SSO_RETURN_LOCALDEV'));
        } elseif ($request->has('uls')) {
            $request->session()->put('return', env('SSO_RETURN_ULS'));
        } else {
            $request->session()->put('return', env('SSO_RETURN_FORUMS'));
        }

        /* If already logged in, don't send to SSO */
        if (\Auth::check()) {
            $return = $request->session()->get("return");
            $request->session()->forget("return");
            ULSHelper::doHandleLogin(\Auth::user()->cid, $return);
            return;
        }

        $this->sso->login(
            config('sso.return'),
            function($key, $secret, $url) {
                session(['SSO_key' => $key]);
                session(['SSO_secret' => $secret]);
                
                header("Location: $url");
                exit;
            }
        );
    }

    public function getReturn(Request $request) {
        if (isset($_REQUEST['cancel'])) {
            $request->session()->forget("SSO");
            $request->session()->forget("return");
            echo "Login request cancelled."; exit;
        }
        $this->sso->validate(
            session('SSO_key'),
            session('SSO_secret'),
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
                    $r = randomPassword();
                    $regOptions = [
                        'member_name' => $user->id,
                        'real_name' => $user->name_first . " " . $user->name_first,
                        'email' => $user->email,
                        'password' => $r,
                        'password_check' => $r,
                        'send_welcome_email' => false,
                        'require' => 'nothing'
                    ];
                    $r = smfapi_registerMember($regOptions);
                    if (is_array($r) || $r == false) {
                        \Log::warning("Failed to create new user: " . base64_encode(serialize($r)) . ", " . base64_encode(serialize($regOptions)));
                        throw new \Exception("Failed to create forum user");
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
                    $member->facility_join = \DB::raw("NOW()");
                    $member->flag_needbasic = 1;
                    $member->flag_xferOverride = 0;
                    $member->flag_homecontroller = (($user->division->code == "USA") ? 1 : 0);
                    $member->save();

                    if ($member->flag_homecontroller) {
                        EmailHelper::sendEmail($member->email, "Welcome to VATUSA", "emails.user.join", []);
                    }
                }

                ULSHelper::doHandleLogin($user->id, $return);
            }
        );
    }
}
