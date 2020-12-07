<?php

namespace App\Http\Controllers\Login;

use App\Action;
use App\Classes\OAuth\VatsimConnect;
use App\Helpers\RoleHelper;
use App\Helpers\SMFHelper;
use App\Helpers\EmailHelper;
use App\Helpers\ULSHelper;
use App\Transfer;
use App\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Class SSOController
 * @package App\Http\Controllers\Login
 */
class SSOController extends Controller
{
    /**
     * @var VatsimConnect
     */
    private $sso;

    /**
     * SSOController constructor.
     */
    public function __construct()
    {
        $this->sso = new VatsimConnect;
    }

    /**
     * @param Request $request
     *
     * @return bool|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector|mixed|void
     * @throws \App\Classes\OAuth\SSOException
     */
    public function getIndex(Request $request)
    {
        if ($request->has("logout")) {
            Auth::logout();
            if (isset($_SERVER['HTTP_REFERER'])) {
                $return = $_SERVER['HTTP_REFERER'];
            } else {
                $return = env('SSO_RETURN_HOME');
            }

            if(app()->environment('staging')) {
                return redirect("https://forums.staging.vatusa.net/api.php?logout=1&return=$return");
            }

            return redirect(app()->environment('dev') || app()->environment('livedev') ? $return : "https://forums.vatusa.net/api.php?logout=1&return=$return");
        }

        /* Lots to check here ... but this is our multi-point redirect */
        if ($request->has('home')) {
            $request->session()->put('return', env('SSO_RETURN_HOME'));
        } elseif ($request->has('agreed')) {
            $request->session()->put('return', env('SSO_RETURN_AGREED', 'https://www.vatusa.net/my/profile'));
            $request->session()->put('fromAgreed', true);
        } elseif ($request->has('homedev')) {
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
        if (Auth::check()) {
            $return = $request->session()->get("return");
            $request->session()->forget("return");

            return ULSHelper::doHandleLogin(Auth::user()->cid, $return);
        }

        return $this->sso->redirect($request);
    }

    public function getReturn(Request $request, $token = null)
    {
        $user = $this->sso->validate($request, $token);
        if ($user instanceof RedirectResponse) {
            return $user;
        }
        $isULS = $request->session()->has(['uls', 'ulsv2']);

        $return = session("return", env("SSO_RETURN_FORUMS"));
        session()->forget("return");

        //Before proceeding, check if user is suspended.
        if ($user->vatsim->rating->id == 0) {
            $error = "You are suspended from the network. Therefore, login has been cancelled.";

            return $isULS ? response($error, 403) : redirect(env('SSO_RETURN_HOME_ERROR'))->with('error', $error);
        }
        if ($user->vatsim->rating->id < 0) {
            $error = "Your account has been disabled by VATSIM. This could be because of inactivity or a duplicate account. 
                Please <a href='https://membership.vatsim.net/'>contact VATSIM Member Services</a> to resolve this issue.";

            return $isULS ? response($error, 403) : redirect(env('SSO_RETURN_HOME_ERROR'))->with('error', $error);
        }
        if (app()->environment("livedev") && !RoleHelper::isVATUSAStaff($user->cid, false, true) && !in_array($user->cid,
                explode(',', env("LIVEDEV_CIDS", "")))) {
            $error = "You are not authorized to access the live development website.";

            return $isULS ? response($error, 403) : redirect(env('SSO_RETURN_HOME_ERROR'))->with('error', $error);
        }
        // Check if user is registered in forums...
        if (!app()->environment('dev') && !app()->environment('livedev')) {
            if (SMFHelper::isRegistered($user->cid)) {
                SMFHelper::updateData($user->cid, $user->personal->name_last, $user->personal->name_first,
                    $user->personal->email);
                SMFHelper::setPermissions($user->cid);
            } else {
                $regOptions = [
                    'member_name'        => $user->cid,
                    'real_name'          => $user->personal->name_first . " " . $user->personal->name_last,
                    'email'              => $user->personal->email,
                    'send_welcome_email' => false,
                    'require'            => 'nothing'
                ];
                $token = ULSHelper::base64url_encode(json_encode($regOptions));
                $signature = hash_hmac("sha512", $token, base64_decode(env("FORUM_SECRET")));
                $signature = ULSHelper::base64url_encode($signature);
                $data = file_get_contents("https://forums.vatusa.net/api.php?register=1&data=$token&signature=$signature");
                if ($data != "OK") {
                    $error = "Unable to create forum data. Please try again later or contact VATUSA12.";

                    return $isULS ? response($error, 401) : redirect(env('SSO_RETURN_HOME_ERROR'))->with('error', $error);

                }
            }
        }

        $member = User::find($user->cid);
        if (!$member) {
            $member = new User();
            $member->cid = $user->cid;
            $member->email = $user->personal->email;
            $member->fname = $user->personal->name_first;
            $member->lname = $user->personal->name_last;
            $member->rating = $user->vatsim->rating->id;
            $member->facility = (($user->vatsim->division->id == "USA") ? "ZAE" : "ZZN");
            $member->facility_join = Carbon::now();
            $member->lastactivity = Carbon::now();
            $member->flag_needbasic = 1;
            $member->flag_xferOverride = 0;
            $member->flag_homecontroller = (($user->vatsim->division->id == "USA") ? 1 : 0);
            $member->save();

            if ($member->flag_homecontroller) {
                EmailHelper::sendEmail($member->email, "Welcome to VATUSA", "emails.user.join", []);

                $log = new Action();
                $log->to = $member->cid;
                $log->log = "Joined division, facility set to ZAE";
                $log->save();
            }
        } else {
            //Update data
            $member->fname = $user->personal->name_first;
            $member->lname = $user->personal->name_last;
            $member->email = $user->personal->email;
            $member->rating = $user->vatsim->rating->id;
            $member->lastactivity = Carbon::now();

            if (!$member->flag_homecontroller && $user->vatsim->division->id == "USA") {
                //User is rejoining
                $transfers = Transfer::where('cid', $member->cid)->where('actiontext', "Left division")
                    ->where('created_at', '>=', Carbon::now()->subHours(48))
                    ->orderBy('created_at', 'DESC');
                if ($transfers->count()) {
                    //Within last 48 hours
                    $t = $transfers->first();
                    $member->addToFacility($t->from);
                    $member->flag_needbasic = 0;

                    $trans = new Transfer();
                    $trans->cid = $member->cid;
                    $trans->to = $t->from;
                    $trans->from = "ZZN";
                    $trans->status = 1;
                    $trans->actiontext = "Rejoined division";
                    $trans->reason = "Rejoined division";
                    $trans->save();

                    $log = new Action();
                    $log->to = $member->cid;
                    $log->log = "Rejoined division within 48 hours, facility set to " . $member->facility;
                    $log->save();
                } elseif (Transfer::where('cid', $member->cid)->where('actiontext', "Left division")
                    ->where('created_at', '>=', Carbon::now()->subMonths(6))
                    ->orderBy('created_at', 'DESC')->count()
                ) {
                    //Within last 6 months but more than 48 hours
                    $member->facility = "ZAE";
                    $member->facility_join = Carbon::now();
                    $member->flag_needbasic = 0;

                    $trans = new Transfer();
                    $trans->cid = $member->cid;
                    $trans->to = "ZAE";
                    $trans->from = "ZZN";
                    $trans->status = 1;
                    $trans->actiontext = "Rejoined division";
                    $trans->reason = "Rejoined division";
                    $trans->save();

                    $log = new Action();
                    $log->to = $member->cid;
                    $log->log = "Rejoined division within 6 months and more than 48 hours, facility set to ZAE";
                    $log->save();
                } else {
                    //More than 6 months ago (or xfr doesn't exist for some reason)
                    $member->facility = "ZAE";
                    $member->facility_join = Carbon::now();
                    $member->flag_needbasic = 1;

                    $trans = new Transfer();
                    $trans->cid = $member->cid;
                    $trans->to = "ZAE";
                    $trans->from = "ZZN";
                    $trans->status = 1;
                    $trans->actiontext = "Rejoined division";
                    $trans->reason = "Rejoined division";
                    $trans->save();

                    $log = new Action();
                    $log->to = $member->cid;
                    $log->log = "Rejoined division after more than 6 months, facility set to ZAE";
                    $log->save();
                }
                // Now let us check to see if they have ever been in a facility.. if not, we need to override the need basic flag.
                if (!Transfer::where('cid', $member->cid)->where('to', 'NOT LIKE', 'ZAE')->where('to',
                    'NOT LIKE', 'ZZN')->exists()) {
                    $member->flag_needbasic = 1;
                }

                $member->flag_homecontroller = 1;
            }

            $member->save();
        }

        return ULSHelper::doHandleLogin($user->cid, $return);
    }
}
