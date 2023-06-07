<?php

namespace App\Http\Controllers\Login;

use App\Action;
use App\Classes\OAuth\VatsimConnect;
use App\CoreAPI\CoreApiHelper;
use App\CoreAPI\CoreAPIHelperException;
use App\Helpers\ExamHelper;
use App\Helpers\RoleHelper;
use App\Helpers\SMFHelper;
use App\Helpers\EmailHelper;
use App\Helpers\ULSHelper;
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

            if (app()->environment('staging')) {
                return redirect(env('SSO_RETURN_FORUMS',
                        'https://forums.staging.vatusa.net/') . "api.php?logout=1&return=$return");
            }

            return redirect(app()->environment('dev') || app()->environment('livedev') ? $return : env('SSO_RETURN_FORUMS',
                    'https://forums.dev.vatusa.net/') . "api.php?logout=1&return=$return");
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
        } elseif ($request->has('moodle')) {
            $request->session()->put('return', env('SSO_RETURN_MOODLE'));
        } elseif ($request->has('moodle-test')) {
            $request->session()->put('return', env('SSO_RETURN_MOODLE_TEST'));
        } elseif ($request->has('localdev')) {
            $request->session()->put('return', env('SSO_RETURN_LOCALDEV'));
        } else {
            $request->session()->put('return', env('SSO_RETURN_FORUMS'));
        }

        /* If already logged in, don't send to SSO */
        if (Auth::check()) {
            $return = $request->session()->get("return");
            $request->session()->forget("return");
            $isTest = $request->has('moodle-test');
            return ULSHelper::doHandleLogin(Auth::user()->cid, $return, $isTest);
        }

        return $this->sso->redirect($request);
    }

    /**
     * @throws CoreAPIHelperException
     */
    public function getReturn(Request $request, $token = null)
    {
        $user = $this->sso->validate($request, $token);
        if ($user instanceof RedirectResponse) {
            return $user;
        }

        $return = session("return", env("SSO_RETURN_FORUMS"));
        session()->forget("return");

        //Before proceeding, check if user is suspended.
        if ($user->vatsim->rating->id == 0) {
            $error = "You are suspended from the network. Therefore, login has been cancelled.";

            return redirect(env('SSO_RETURN_HOME_ERROR'))->with('error', $error);
        }
        if ($user->vatsim->rating->id < 0) {
            $error = "Your account has been disabled by VATSIM. This could be because of inactivity or a duplicate account. 
                Please <a href='https://membership.vatsim.net/'>contact VATSIM Member Services</a> to resolve this issue.";

            return redirect(env('SSO_RETURN_HOME_ERROR'))->with('error', $error);
        }
        if (app()->environment("livedev") && !RoleHelper::isVATUSAStaff($user->cid, false,
                true) && !in_array($user->cid,
                explode(',', env("LIVEDEV_CIDS", "")))) {
            $error = "You are not authorized to access the live development website.";

            return redirect(env('SSO_RETURN_HOME_ERROR'))->with('error', $error);
        }

        $member = User::find($user->cid);
        $updateName = true;

        //Process Preferred Name
        if ($member && $member->prefname) {
            if (Carbon::now()->subDays(14)->greaterThanOrEqualTo($member->prefname_date)) {
                //Expired
                $member->prefname = 0;
                $member->prefname_date = null;
                $member->save();
            } else {
                $updateName = false;
            }
        }

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
            $member->cert_update = 1;
            $member->save();

            if ($member->flag_homecontroller) {
                EmailHelper::sendEmail($member->email, "Welcome to VATUSA", "emails.user.join", []);

                $log = new Action();
                $log->to = $member->cid;
                $log->log = "Joined division, facility set to ZAE";
                $log->save();
            }
        } else {
            $passedBasic = ExamHelper::academyPassedExam($member->cid, "basic", 0,6);
            //Update data
            if ($updateName) {
                $member->fname = ucfirst(trim($user->personal->name_first));
                $member->lname = ucwords(trim($user->personal->name_last));
            }
            $member->email = $user->personal->email;
            $member->rating = $user->vatsim->rating->id;
            $member->lastactivity = Carbon::now();

            if ($member->facility == "ZAE" && $member->facility_join->diffInMonths(Carbon::now()) > 6 && !$passedBasic) {
                $member->flag_needbasic = 1;
            }

            if ($passedBasic) {
                $member->flag_needbasic = 0;
            }

            if (!$member->flag_homecontroller && $user->vatsim->division->id == "USA") {

                if (Carbon::createFromFormat('Y-m-d H:i:s', $member->facility_join)->diffInMonths(Carbon::now()) >= 6) {
                    // Has been gone at least 6 months
                    $member->flag_needbasic = 1;
                    EmailHelper::sendEmail($member->email, "Welcome to VATUSA", "emails.user.join", []);
                }
                $transfer = CoreApiHelper::createTransferRequest(
                    $member->cid, "ZAE", "Rejoined division", 0,true);

                $member->flag_homecontroller = 1;
            }

            $member->save();
        }
        // Check if user is registered in forums...
        if (!app()->environment('dev') && !app()->environment('livedev')) {
            if (SMFHelper::isRegistered($user->cid)) {
                SMFHelper::updateData($user->cid, $updateName ? $user->personal->name_last : $member->lname,
                    $updateName ? $user->personal->name_first : $member->fname,
                    $user->personal->email);
                SMFHelper::setPermissions($user->cid);
            } else {
                $regOptions = [
                    'member_name'        => $user->cid,
                    'real_name'          => $updateName ? $user->personal->name_first . " " . $user->personal->name_last : $member->fname . " " . $member->lname,
                    'email'              => $user->personal->email,
                    'send_welcome_email' => false,
                    'require'            => 'nothing'
                ];
                $token = ULSHelper::base64url_encode(json_encode($regOptions));
                $signature = hash_hmac("sha512", $token, base64_decode(env("FORUM_SECRET")));
                $signature = ULSHelper::base64url_encode($signature);
                $data = file_get_contents(env('SSO_RETURN_FORUMS',
                        'https://forums.vatusa.net/') . "api.php?register=1&data=$token&signature=$signature");
                if ($data != "OK") {
                    $error = "Unable to create forum data. Please try again later or contact VATUSA12.";

                    return redirect(env("SSO_RETURN_HOME_ERROR"))->with('error', $error);
                }
            }
        }

        return ULSHelper::doHandleLogin($user->cid, $return);
    }
}
