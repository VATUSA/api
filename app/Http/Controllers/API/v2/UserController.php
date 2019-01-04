<?php

namespace App\Http\Controllers\API\v2;

use App\Action;
use App\ExamResults;
use App\Helpers\AuthHelper;
use App\Helpers\CERTHelper;
use App\Helpers\EmailHelper;
use App\Helpers\RatingHelper;
use App\Helpers\RoleHelper;
use App\Promotion;
use App\Role;
use App\TrainingChapter;
use App\TrainingProgress;
use App\Transfer;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Facility;

/**
 * Class UserController
 * @package App\Http\Controllers\API\v2
 */
class UserController extends APIController
{
    /**
     * @SWG\Get(
     *     path="/user/(cid)",
     *     summary="(DONE) Get data about user",
     *     description="(DONE) Get user's information. Email field requires authentication as staff member.
           Broadcast opt-in status requires API key. Prevent Staff Assignment field requires authentication
           as senior staff.",
     *     produces={"application/json"}, tags={"user"},
     *     @SWG\Parameter(name="cid",in="path",required=true,type="string",description="Cert ID"),
     *     @SWG\Response(
     *         response="404",
     *         description="Not found",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Not found"}},
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(ref="#/definitions/User")
     *     )
     * )
     */
    public function getIndex(Request $request, $cid)
    {
        $user = User::find($cid);
        if (!$user) {
            return response()->api(generate_error("Not found"), 404);
        }
        $data = $user->toArray();
        if (!AuthHelper::validApiKeyv2($request->input('apikey', null))) {
            $data['flag_broadcastOptedIn'] = null;
        }
        if (!(\Auth::check() && RoleHelper::isFacilityStaff(\Auth::user()->cid, \Auth::user()->facility))) {
            $data['email'] = null;
        }
        if (!(\Auth::check() && RoleHelper::isSeniorStaff(\Auth::user()->cid, \Auth::user()->facility))) {
            $data['flag_preventStaffAssign'] = null;
        }

        return response()->api($data);
    }

    /**
     * @param string $facility
     * @param string $role
     *
     * @return array|string
     *
     * @SWG\Get(
     *     path="/user/roles/(facility)/(role)",
     *     summary="(DONE) Get users assigned to specific role",
     *     description="(DONE) Get users assigned to specific role",
     *     produces={"application/json"},
     *     tags={"user","role"},
     *     @SWG\Parameter(name="facility", in="path", required=true, type="string", description="Facility IATA ID"),
     *     @SWG\Parameter(name="role", in="path", required=true, type="string", description="Role"),
     *     @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(
     *             type="array",
     *             @SWG\Items(
     *                 type="object",
     *                 @SWG\Property(property="cid",type="integer",description="CERT ID of user"),
     *                 @SWG\Property(property="lname",type="string",description="Last name"),
     *                 @SWG\Property(property="fname",type="string",description="First name"),
     *             ),
     *         ),
     *     )
     * ),
     */
    public function getRoleUsers($facility, $role)
    {
        $roles = Role::where('facility', $facility)->where('role', $role)->get();
        $return = [];
        foreach ($roles as $role) {
            $return[] = ['cid' => $role->cid, 'lname' => $role->user->lname, 'fname' => $role->user->fname];
        }

        return response()->api($return);
    }

    /**
     * @param int    $cid
     * @param string $facility
     * @param string $role
     *
     * @return array|string
     *
     * @SWG\Post(
     *     path="/user/(cid)/roles/(facility)/(role)",
     *     summary="(DONE) Assign new role. Requires JWT or Session Cookie",
     *     description="(DONE) Assign new role. Requires JWT or Session Cookie (required role: for FE, EC, WM roles:
     *     ATM, DATM, for MTR roles: TA, for all other roles: VATUSA STAFF)", produces={"application/json"},
     *     tags={"user","role"}, security={"jwt","session"},
     *     @SWG\Parameter(name="cid", in="path", required=true, type="integer", description="CERT ID"),
     *     @SWG\Parameter(name="facility", in="path", required=true, type="string", description="Facility IATA ID"),
     *     @SWG\Parameter(name="role", in="path", required=true, type="string", description="Role"),
     *     @SWG\Response(
     *         response="401",
     *         description="Unauthorized",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Unauthorized"}},
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Forbidden",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Forbidden"}},
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(ref="#/definitions/OK"),
     *         examples={"application/json":{"status"="OK"}}
     *     )
     * )
     */
    public function postRole($cid, $facility, $role)
    {
        if (!\Auth::check()) {
            return response()->json(generate_error("Unauthorized"), 401);
        }

        $facility = Facility::find($facility);
        if (!$facility || ($facility->active != 1 && $facility->id != "ZHQ" && $facility->id != "ZAE")) {
            return response()->api(generate_error("Facility not found or invalid"), 404);
        }

        if (!RoleHelper::canModify(\Auth::user(), $facility, $role)) {
            return response()->api(generate_error("Forbidden"), 403);
        }

        if (in_array($role, ['ATM', 'DATM', 'TA', 'EC', 'FE', 'WM'])) {
            if (Role::where("facility", $facility->id)->where("role", $role)->count() == 0) {
                if (!EmailHelper::isStaticForward("$facility-$role@vatusa.net")) {
                    // New person, setup the forward
                    $email = strtolower($facility->id . "-" . $role . "@vatusa.net");
                    if (!EmailHelper::deleteForward($email)) {
                        \Log::critical("Couldn't delete forward for $email");
                    }
                }
            }
        }

        $r = new Role();
        $r->facility = $facility->id;
        $r->cid = $cid;
        $r->role = $role;
        $r->save();

        log_action($cid, "Assigned to role $role for $facility->id by " . \Auth::user()->fullname());

        return response()->api(['status' => 'OK']);
    }

    /**
     * @return array|string
     *
     * @SWG\Delete(
     *     path="/user/(cid)/roles/(facility)/(role)",
     *     summary="(DONE) Delete role. Requires JWT or Session Cookie",
     *     description="(DONE) Delete role. Requires JWT or Session Cookie (required role: for FE, EC, WM roles: ATM,
     *     DATM, for MTR roles: TA, for all other roles: VATUSA STAFF)", produces={"application/json"}, tags={"user",
     *     "role"}, security={"jwt","session"},
     *     @SWG\Parameter(name="cid", in="path", required=true, type="integer", description="CERT ID"),
     *     @SWG\Parameter(name="facility", in="path", required=true, type="string", description="Facility IATA ID"),
     *     @SWG\Parameter(name="role", in="path", required=true, type="string", description="Role"),
     *     @SWG\Response(
     *         response="401",
     *         description="Unauthorized",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Unauthorized"}},
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Forbidden",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Forbidden"}},
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Not found, role may not be assigned",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Not found"}},
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(ref="#/definitions/OK"),
     *         examples={"application/json":{"status"="OK"}}
     *     )
     * )
     */
    public function deleteRole($cid, $facility, $role)
    {
        if (!\Auth::check()) {
            return response()->json(generate_error("Unauthorized"), 401);
        }

        //$role = strtolower($role);

        $fac = Facility::find($facility);
        if (!$fac || ($fac->active != 1 && $fac->id != "ZHQ" && $fac->id != "ZAE")) {
            return response()->api(generate_error("Facility not found or invalid"), 404);
        }

        if (!RoleHelper::canModify(\Auth::user(), $fac, $role)) {
            return response()->api(generate_error("Forbidden"), 403);
        }

        if (!RoleHelper::has($cid, $facility, $role)) {
            return response()->api(generate_error("Not found"), 404);
        }

        Role::where('facility', $facility)->where('role', $role)->where('cid', $cid)->delete();

        if (in_array($role, ['atm', 'datm', 'ta', 'ec', 'fe', 'wm'])) {
            if (Role::where('facility', $facility)->where('role', $role)->count() === 0) {
                if (!EmailHelper::isStaticForward("$facility-$role@vatusa.net")) {
                    EmailHelper::deleteForward("$facility-$role@vatusa.net");
                    $destination = "$facility-sstf@vatusa.net";
                    if ($role === "datm") {
                        $destination = "$facility-atm@vatusa.net";
                    }
                    if ($role === "atm") {
                        $destination = "vatusa" . $fac->region . "@vatusa.net";
                    }
                    EmailHelper::setForward("$facility-$role@vatusa.net", $destination);
                }
            }
        }

        log_action($cid, "Removed from role $role for $fac->id by " . \Auth::user()->fullname());

        return response()->api(['status' => 'OK']);
    }

    /**
     * @return array|string
     *
     * @SWG\Post(
     *     path="/user/(cid)/transfer",
     *     summary="(DONE) Submit transfer request. CORS Restricted Requires JWT or Session Cookie",
     *     description="(DONE) Submit transfer request. CORS Restricted Requires JWT or Session Cookie (self or VATUSA
     *     staff)", produces={"application/json"}, tags={"user","transfer"}, security={"jwt","session"},
     *     @SWG\Parameter(name="cid", in="path", required=true, type="integer", description="CERT ID"),
     *     @SWG\Parameter(name="facility", in="formData", required=true, type="string", description="Facility IATA
     *                                     ID"),
     *     @SWG\Parameter(name="reason", in="formData", required=true, type="string", description="Reason for transfer
     *                                   request"),
     *     @SWG\Response(
     *         response="400",
     *         description="Malformed request (missing field?)",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Malformed request"}},
     *     ),
     *     @SWG\Response(
     *         response="401",
     *         description="Unauthorized",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Unauthorized"}},
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Forbidden",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Forbidden"}},
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Facility not found",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Not found"}},
     *     ),
     *     @SWG\Response(
     *         response="409",
     *         description="There was a conflict, usually meaning the user has a pending transfer request or is not
     *         eligible",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Conflict"}},
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(ref="#/definitions/OK"),
     *         examples={"application/json":{"status"="OK"}}
     *     )
     * )
     */
    public function postTransfer($cid)
    {
        if (!\Auth::check()) {
            return response()->json(generate_error("Unauthorized"), 401);
        }
        if (\Auth::user()->cid != $cid && !RoleHelper::isVATUSAStaff(\Auth::user()->cid)) {
            return response()->json(generate_error("Forbidden"), 403);
        }
        $user = User::find($cid);
        if (!$user) {
            return response()->json(generate_error("Not found"), 404);
        }
        if (!$user->transferEligible()) {
            return response()->json(generate_error("Conflict"), 409);
        }

        $facility = request()->get("facility", null);
        $reason = request()->get("reason", null);
        if (!$facility || !Facility::find()) {
            return response()->json(generate_error("Not found"), 404);
        }
        if (strlen($reason) < 3) {
            return response()->json(generate_error("Malformed request"), 400);
        }

        $transfer = new Transfer();
        $transfer->cid = $cid;
        $transfer->to = $facility;
        $transfer->from = $user->facility;
        $transfer->reason = $reason;
        $transfer->save();

        if ($user->flag_xferoverride) {
            $user->setTransferOverride(0);
        }

        $emails = [];
        if ($transfer->to != "ZAE" && $transfers->to != "ZHQ") {
            $emails[] = $transfer->to . "-sstf@vatusa.net";
            $emails[] = "vatusa" . $transfer->toFac->region() . "@vatusa.net";
        }
        if ($transfer->from != "ZAE" && $transfers->from != "ZHQ") {
            $emails[] = $transfer->to . "-sstf@vatusa.net";
            $emails[] = "vatusa" . $transfer->fromFac->region() . "@vatusa.net";
        }

        \Mail::to($emails)->send(new TransferRequested($transfer));

        return response()->json(['status' => 'OK']);
    }

    /**
     * @return array|string
     *
     * @SWG\Get(
     *     path="/user/(cid)/transfer/checklist",
     *     summary="(DONE) Get user's transfer checklist. Requires JWT, API Key, or Session Cookie",
     *     description="(DONE) Get user's checklist. Requires JWT, API Key, or Session Cookie (required role [N/A for
     *     apikey]: ATM, DATM, WM)", produces={"application/json"}, tags={"user","transfer"},
     *     security={"jwt","session","apikey"},
     *     @SWG\Parameter(name="cid", in="path", required=true, type="integer", description="CERT ID"),
     *     @SWG\Response(
     *         response="401",
     *         description="Unauthorized",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Unauthorized"}},
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Forbidden",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Forbidden"}},
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(
     *             type="array",
     *             @SWG\Items(
     *                 type="object",
     *                 @SWG\Property(property="item", type="string", description="Checklist checked item"),
     *                 @SWG\Property(property="result", type="string", description="Result of check (OK, FAIL)"),
     *             )
     *         ),
     *     )
     * )
     */
    public function getTransferChecklist($cid)
    {
        $hasValidApiKey = AuthHelper::validApiKeyv2(request()->input('apikey', null));
        if (!$hasValidApiKey && !\Auth::check()) {
            return response()->json(generate_error("Unauthorized"), 401);
        }
        if ($hasValidApiKey || (\Auth::check() &&
                (
                    \Auth::user()->cid == $cid ||
                    RoleHelper::isVATUSAStaff(\Auth::user()->cid) ||
                    RoleHelper::has(\Auth::user()->cid, \Auth::user()->facility, ["ATM", "DATM", "WM"])
                )
            )) {
            $check = [];
            $overall = User::find($cid)->transferEligible($check);

            return response()->json(array_merge($check, ['overall' => $overall]));
        }

        return response()->json(generate_error("Forbidden"), 403);
    }

    /**
     * @return array|string
     *
     * @SWG\Post(
     *     path="/user/(cid)/rating",
     *     summary="(DONE) Submit rating change. Requires JWT or Session Cookie",
     *     description="(DONE) Submit rating change. Requires JWT or Session Cookie (required role: ATM, DATM, TA, INS,
     *     VATUSA STAFF)", produces={"application/json"}, tags={"user","rating"}, security={"jwt","session"},
     *     @SWG\Parameter(name="cid", in="path", required=true, type="integer", description="CERT ID"),
     *     @SWG\Parameter(name="rating", in="formData", required=true, type="string", description="Rating to change
     *                                   rating to"),
     *     @SWG\Parameter(name="examDate", in="formData", type="string", description="Date of exam (format, YYYY-MM-DD)
     *                                     required for C1 and below"),
     *     @SWG\Parameter(name="examiner", in="formData", type="integer", description="CID of Examiner, if not provided
     *                                     or null will default to authenticated user, required for C1 and below"),
     *     @SWG\Parameter(name="position", in="formData", type="string", description="Position sat during exam,
     *                                     required for C1 and below"),
     *     @SWG\Response(
     *         response="401",
     *         description="Unauthorized",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Unauthorized"}},
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Forbidden",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Forbidden"}},
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Not found",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Not found"}},
     *     ),
     *     @SWG\Response(
     *         response="409",
     *         description="Conflict, when current rating and promoted rating are the same or demotion not possible",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Conflict"}},
     *     ),
     *     @SWG\Response(
     *         response="412",
     *         description="Precondition failed (not eligible)",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Precondition failed"}},
     *     ),
     *     @SWG\Response(
     *         response="500",
     *         description="CERT error, contact data services team",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Internal server error"}},
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(ref="#/definitions/OK"),
     *         examples={"application/json":{"status"="OK"}}
     *     )
     * )
     */
    public function postRating($cid)
    {
        if (!\Auth::check()) {
            return response()->api(generate_error("Unauthorized"), 401);
        }
        $user = User::find($cid);
        if (!$user) {
            return response()->api(generate_error("Not found"), 404);
        }
        $rating = request()->input("rating", null);
        if (!$rating) {
            return response()->api(generate_error("Malformed request"), 400);
        }
        //$cid = request()->input("cid");
        $examDate = request()->input("examDate", null); // Will be checked when appropriate
        $examiner = request()->input("examiner", null); // Will be checked when appropriate
        $position = request()->input("position", null); // Will be checked when appropriate

        if (!is_numeric($rating)) {
            $rating = RatingHelper::shortToInt($rating);
        }
        if ($rating > RatingHelper::shortToInt("I3")) {
            // Do not process ratings above I3... ever
            return response()->api(generate_error("Malformed request"), 400);
        }

        // C1->I1/I3 changes
        if ($rating >= RatingHelper::shortToInt("I1")) {
            // Can only be executed by VATUSA Staff
            if (!RoleHelper::isVATUSAStaff()) {
                return response()->api(generate_error("Forbidden"), 403);
            }

            Promotion::process($cid, \Auth::user()->cid, $rating);
            $return = CERTHelper::changeRating($cid, $rating, true);
            if ($return) {
                return response()->api(["status" => "OK"]);
            } else {
                return response()->api(["status" => "Internal server error"], 500);
            }
        }

        // OBS-C1 changes
        if (!RoleHelper::isVATUSAStaff() &&
            !RoleHelper::has(\Auth::user()->cid, \Auth::user()->facility, ["ATM", "DATM", "TA"]) &&
            !RoleHelper::isInstructor(\Auth::user()->cid)) {

            return response()->api(generate_error("Forbidden" . json_encode(RoleHelper::isVATUSAStaff())), 403);
        }
        if ($user->rating >= $rating || $user->rating + 1 != $rating) {
            return response()->api(generate_error("Conflict"), 409);
        }

        // @TODO Write validation rules
        if (!$examDate || !$examiner || !$position) {
            return response()->api(generate_error("Malformed request"), 400);
        }

        if (!$user->promotionEligible()) {
            return response()->api(generate_error("Precondition failed"), 412);
        }
        Promotion::process($user->cid, \Auth::user()->cid, $user->rating + 1, $user->rating, $examDate, $examiner,
            $position);
        $return = CERTHelper::changeRating($cid, $rating, true);
        if ($return) {
            return response()->api(["status" => "OK"]);
        } else {
            return response()->api(["status" => "Internal server error"], 500);
        }
    }

    /**
     * @return array|string
     *
     * @SWG\Get(
     *     path="/user/(cid)/rating/history",
     *     summary="(DONE) Get user's rating history. Requires JWT, API Key or Session Cookie",
     *     description="(DONE) Get user's rating history. Requires JWT, API Key or Session Cookie (required role: [N/A
     *     for API Key] ATM, DATM, TA, INS, VATUSA STAFF)", produces={"application/json"}, tags={"user","rating"},
     *     security={"jwt","session","apikey"},
     *     @SWG\Parameter(name="cid", in="path", required=true, type="integer", description="CERT ID"),
     *     @SWG\Response(
     *         response="401",
     *         description="Unauthorized",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Unauthorized"}},
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Forbidden",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Forbidden"}},
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Not found",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Not found"}},
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(
     *             type="array",
     *             @SWG\Items(ref="#/definitions/Promotion"),
     *         ),
     *         examples={"application/json":{{"id": 9486,"cid": 876594,"grantor": 111111,"to": 8,"from":
     *         10,"created_at": "2011-09-06T04:28:51+00:00","exam": "0000-00-00","examiner": 0,"position": ""}}},
     *     )
     * )
     */
    public function getRatingHistory($cid)
    {
        $hasValidApiKey = AuthHelper::validApiKeyv2(request()->input('apikey', null));
        if (!User::find($cid)) {
            return response()->json(generate_error("Not found"), 404);
        }
        if (!$hasValidApiKey && !\Auth::check()) {
            return response()->json(generate_error("Unauthorized"), 401);
        }
        if (!$hasValidApiKey && !(\Auth::check() &&
                (
                    \Auth::user()->cid == $cid ||
                    RoleHelper::isVATUSAStaff(\Auth::user()->cid) ||
                    RoleHelper::has(\Auth::user()->cid, \Auth::user()->facility, ["ATM", "DATM", "WM"])
                )
            )) {

            return response()->api(generate_error("Forbidden"), 403);
        }

        $history = Promotion::where('cid', $cid)->orderBy('created_at', 'desc')->get()->toArray();

        return response()->api($history);
    }

    /**
     * @return array|string
     *
     * @SWG\Get(
     *     path="/user/(cid)/log",
     *     summary="(DONE) Get controller's action log. CORS Restricted Requires JWT or Session Cookie",
     *     description="(DONE) Get controller's action log. CORS Restricted Requires JWT or Session Cookie (required
     *     role: ATM, DATM, VATUSA STAFF)", produces={"application/json"}, tags={"user"}, security={"jwt","session"},
     *     @SWG\Parameter(name="cid", in="path", required=true, type="integer", description="CERT ID"),
     *     @SWG\Parameter(name="entry", in="formData", required=true, type="string", description="Entry to log"),
     *     @SWG\Response(
     *         response="401",
     *         description="Unauthorized",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Unauthorized"}},
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Forbidden",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Forbidden"}},
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Not found",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Not found"}},
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(
     *             type="array",
     *             @SWG\Items(ref="#/definitions/Action"),
     *         ),
     *         examples={"application/json":{{"id": 579572,"to": 1394143,"log": "Joined division, facility set to ZAE
     *         by CERTSync","created_at": "2017-06-01T00:02:09+00:00"}}}
     *     )
     * )
     */
    public function getActionLog($cid)
    {
        if (!User::find($cid)) {
            return response()->json(generate_error("Not found"), 404);
        }
        if (!\Auth::check()) {
            return response()->json(generate_error("Unauthorized"), 401);
        }
        if (!(\Auth::check() &&
            (
                \Auth::user()->cid == $cid ||
                RoleHelper::isVATUSAStaff(\Auth::user()->cid) ||
                RoleHelper::has(\Auth::user()->cid, \Auth::user()->facility, ["ATM", "DATM"])
            )
        )) {

            return response()->json(generate_error("Forbidden"), 403);
        }

        $logs = Action::where("to", $cid)->get()->toArray();

        return response()->json($logs);
    }

    /**
     * @return array|string
     *
     * @SWG\Post(
     *     path="/user/(cid)/log",
     *     summary="(DONE) Submit entry to controller's action log. CORS Restricted Requires JWT or Session Cookie",
     *     description="(DONE) Submit entry to controller's action log. CORS Restricted Requires JWT or Session Cookie
     *     (required role: ATM, DATM, VATUSA STAFF)", produces={"application/json"}, tags={"user"},
     *     security={"jwt","session"},
     *     @SWG\Parameter(name="cid", in="path", required=true, type="integer", description="CERT ID"),
     *     @SWG\Parameter(name="entry", in="formData", required=true, type="string", description="Entry to log"),
     *     @SWG\Response(
     *         response="400",
     *         description="Malformed request",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Malformed request"}},
     *     ),
     *     @SWG\Response(
     *         response="401",
     *         description="Unauthorized",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Unauthorized"}},
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Forbidden",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Forbidden"}},
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(ref="#/definitions/OK"),
     *         examples={"application/json":{"status"="OK"}}
     *     )
     * )
     */
    public function postActionLog($cid)
    {
        $entry = request()->input("entry", null);
        if (!$entry) {
            return response()->json(generate_error("Malformed request"), 400);
        }
        if (!\Auth::check()) {
            return response()->json(generate_error("Unauthorized"), 401);
        }
        if (!RoleHelper::isVATUSAStaff(\Auth::user()->cid) && !RoleHelper::has(\Auth::user()->cid,
                \Auth::user()->facility, ["ATM", "DATM"])) {
            return response()->json(generate_error("Forbidden"), 403);
        }

        log_action($cid, $entry);

        return response()->json(['status' => 'OK']);
    }

    /**
     * @return array|string
     *
     * @SWG\Get(
     *     path="/user/(cid)/transfer/history",
     *     summary="(DONE) Get user's transfer history. Requires JWT, API Key or Session Cookie",
     *     description="(DONE) Get user's history. Requires JWT, API Key or Session Cookie (required role: [N/A for API
     *     Key] ATM, DATM, TA, WM, VATUSA STAFF)", produces={"application/json"}, tags={"user","transfer"},
     *     security={"jwt","session","apikey"},
     *     @SWG\Parameter(name="cid", in="path", required=true, type="integer", description="CERT ID"),
     *     @SWG\Response(
     *         response="403",
     *         description="Forbidden",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Forbidden"}},
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Not found",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Not found"}},
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(
     *             type="array",
     *             @SWG\Items(
     *                 ref="#/definitions/Transfer"
     *             )
     *         ),
     *         examples={"application/json":{{"id":673608,"cid":1055319,"to":"ZAE","from":"ZNY","reason":"Removed for
     *         inactivity.","status":1,"actiontext":"Removed for
     *         inactivity.","actionby":0,"created_at":"2017-01-01T12:06:27+00:00","updated_at":"2017-01-01T12:06:27+00:00"}}},
     *     )
     * )
     */
    public function getTransferHistory($cid)
    {
        if (!User::find($cid)) {
            return response()->json(generate_error("Not found"), 404);
        }

        if (!(\Auth::check() &&
            (
                \Auth::user()->cid == $cid ||
                RoleHelper::isVATUSAStaff(\Auth::user()->cid) ||
                RoleHelper::has(\Auth::user()->cid, \Auth::user()->facility, ["ATM", "DATM", "TA", "WM"])
            )
        )) {

            $transfers = Transfer::where('cid', $cid)->orderBy('created_at', 'desc')->get()->toArray();

            return response()->json($transfers);
        }

        return response()->json(generate_error("Forbidden"), 403);
    }

    /**
     * @return array|string
     *
     * @SWG\Get(
     *     path="/user/(cid)/cbt/history",
     *     summary="(DONE) Get user's CBT history.",
     *     description="(DONE) Get user's CBT history.",
     *     produces={"application/json"},
     *     tags={"user","cbt"},
     *     security={"jwt","session","apikey"},
     *     @SWG\Parameter(name="cid", in="path", required=true, type="integer", description="CERT ID"),
     *     @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(
     *             type="array",
     *             @SWG\Items(
     *                 ref="#/definitions/TrainingProgress"
     *             )
     *         ),
     *         examples={"application/json":{{"cid":876594,"chapterid":51,"date":"2016-09-11T23:02:42+00:00"}}},
     *     )
     * )
     */
    public function getCBTHistory($cid)
    {
        $data = TrainingProgress::where("cid", $cid)->get()->toArray();

        return response()->json($data);
    }

    /**
     * @return array|string
     *
     * @SWG\Get(
     *     path="/user/(cid)/cbt/progress/(blockId)",
     *     summary="(DONE) Get user's CBT history for block ID.",
     *     description="(DONE) Get user's CBT history for block ID.",
     *     produces={"application/json"},
     *     tags={"user","cbt"},
     *     @SWG\Parameter(name="cid", in="path", required=true, type="integer", description="CERT ID"),
     *     @SWG\Parameter(name="blockId", in="query", type="integer", description="Mark progress of specific Block
     *                                    ID"),
     *     @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(
     *             type="array",
     *             @SWG\Items(
     *                 type="object",
     *                 @SWG\Property(property="chapterId", type="integer"),
     *                 @SWG\Property(property="chapterName", type="string"),
     *                 @SWG\Property(property="completed", type="boolean"),
     *                 @SWG\Property(property="date", type="string", description="Null if not completed, otherwise date
     *                                                completed")
     *             )
     *         ),
     *         examples={"application/json":{{"chapterId":97,"chapterName":"Basic ATC/S1
     *         Orientation","completed":true,"date":"2017-04-07T19:25:44+00:00"}}}
     *     )
     * )
     */
    public function getCBTProgress($cid, $blockId)
    {
        $chapters = TrainingChapter::where('blockid', $blockId)->get();
        $data = [];
        foreach ($chapters as $chapter) {
            $tp = TrainingProgress::where('cid', $cid)->where('chapterid', $chapter->id)->first();
            $data[] = [
                'chapterId'   => $chapter->id,
                'chapterName' => $chapter->name,
                'completed'   => (!$tp) ? false : true,
                'date'        => (!$tp) ? null : $tp->date
            ];
        }

        return response()->json($data);
    }

    /**
     * @return array|string
     *
     * @SWG\Put(
     *     path="/user/(cid)/cbt/progress/(blockId)/(chapterId)",
     *     summary="(DONE) Updates user's CBT history. Requires JWT, API Key or Session Cookie",
     *     description="(DONE) Updates user's CBT history. Requires JWT, API Key or Session Cookie (must originate from
     *     user if not using API Key)", produces={"application/json"}, tags={"user","cbt"},
     *     security={"jwt","session","apikey"},
     *     @SWG\Parameter(name="cid", in="path", required=true, type="integer", description="CERT ID"),
     *     @SWG\Parameter(name="blockId", in="query", type="integer", description="Mark progress of specific Block
     *                                    ID"),
     *     @SWG\Parameter(name="chapterId", in="query", type="integer", description="Mark progress of specific Chapter
     *                                      ID"),
     *     @SWG\Response(
     *         response="401",
     *         description="Unauthorized",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Unauthorized"}},
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Forbidden",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Forbidden"}},
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(ref="#/definitions/OK"),
     *         examples={"application/json":{"status"="OK"}}
     *     )
     * )
     */
    public function putCBTProgress($cid, $blockId, $chapterId)
    {
        $apikey = AuthHelper::validApiKeyv2(request()->input('apikey', null));
        if (!\Auth::check() && !$apikey) {
            return response()->json(generate_error("Unautheicated"), 401);
        }

        if (!\Auth::user()->cid != $cid && !request()->has("apikey")) {
            return response()->json(generate_error("Forbidden"), 403);
        }

        $tp = new TrainingProgress();
        $tp->cid = $cid;
        $tp->chapterid = $chapterId;
        $tp->date = Carbon::now();
        $tp->save();

        return response()->api(['status' => 'OK']);
    }

    /**
     * @return array|string
     *
     * @SWG\Get(
     *     path="/user/(cid)/exam/history",
     *     summary="(DONE) Get user's exam history. Requires JWT, API Key or Session Cookie",
     *     description="(DONE) Get user's exam history. Requires JWT, API Key or Session Cookie (required role: [N/A
     *     for API Key] ATM, DATM, TA, INS, VATUSA STAFF)", produces={"application/json"}, tags={"user","exam"},
     *     security={"jwt","session","apikey"},
     *     @SWG\Parameter(name="cid", in="path", required=true, type="integer", description="CERT ID"),
     *     @SWG\Response(
     *         response="401",
     *         description="Unauthorized",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Unauthorized"}},
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Forbidden",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Forbidden"}},
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(
     *             type="array",
     *             @SWG\Items(
     *                 ref="#/definitions/ExamResults"
     *             )
     *         ),
     *         examples={"application/json":{{"id":18307,"exam_id":7,"exam_name":"VATUSA - Basic ATC
     *         Quiz","cid":876594,"score":88,"passed":1,"date":"2009-09-14T04:17:37+00:00"}}},
     *     )
     * )
     */
    public function getExamHistory($cid)
    {
        $hasValidApiKey = AuthHelper::validApiKeyv2(request()->input('apikey', null));
        if (!\Auth::check() && !$hasValidApiKey) {
            return response()->json(generate_error("Unauthenticated"), 401);
        }

        if (!$hasValidApiKey && $cid != \Auth::user()->cid && !RoleHelper::isVATUSAStaff() && !RoleHelper::isFacilityStaff() && !RoleHelper::isInstrcutor(\Auth::user()->cid)) {
            return response()->json(generate_error("Forbidden"), 403);
        }

        $results = ExamResults::where('cid', $cid)->orderBy('date', 'desc')->get()->toArray();

        return response()->json($results);
    }
}
