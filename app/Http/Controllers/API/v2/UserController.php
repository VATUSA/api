<?php

namespace App\Http\Controllers\API\v2;

use App\Action;
use App\Helpers\AuthHelper;
use App\Helpers\CERTHelper;
use App\Helpers\EmailHelper;
use App\Helpers\Helper;
use App\Helpers\RatingHelper;
use App\Helpers\RoleHelper;
use App\Promotion;
use App\Role;
use App\Transfer;
use App\User;
use Auth;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Facility;
use Illuminate\Support\Facades\Validator;

/**
 * Class UserController
 * @package App\Http\Controllers\API\v2
 */
class UserController extends APIController
{
    /**
     * @SWG\Get(
     *     path="/user/(cid)",
     *     summary="Get user's information.",
     *     description="Get user's information. Email field, broadcast opt-in status, and visiting facilities require authentication as staff member or API key.
    Prevent staff assigment flag requires authentication as senior staff.",
     *     produces={"application/json"}, tags={"user"},
     * @SWG\Parameter(name="cid",in="path",required=true,type="string",description="Cert ID"),
     * @SWG\Response(
     *         response="404",
     *         description="Not found",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Not found"}},
     *     ),
     * @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(ref="#/definitions/User")
     *     )
     * )
     *
     * @param \Illuminate\Http\Request $request
     * @param                          $cid
     *
     * @return array|string
     */
    public function getIndex(Request $request, $cid)
    {
        $user = $request->exists('d') ? User::where('discord_id', $cid)->first() : User::find($cid);
        $isFacStaff = Auth::check() && RoleHelper::isFacilityStaff(Auth::user()->cid, Auth::user()->facility);
        $isSeniorStaff = Auth::check() && RoleHelper::isSeniorStaff(Auth::user()->cid, Auth::user()->facility);

        if (!$user) {
            return response()->api(generate_error("Not found"), 404);
        }
        $data = $user->toArray();

        if (!AuthHelper::validApiKeyv2($request->input('apikey', null)) && !$isFacStaff) {
            //API Key Required
            $data['flag_broadcastOptedIn'] = null;
            $data['email'] = null;
        }
        if (!$isSeniorStaff) {
            //Senior Staff Only
            $data['flag_preventStaffAssign'] = null;
        }

        //Add rating_short property
        $data['rating_short'] = RatingHelper::intToShort($data["rating"]);

        // Get Facilties CID is Visiting
        $data['visiting_facilities'] = $user->visits->toArray();

        //Is Mentor
        $data['isMentor'] = $user->roles->where("facility", $user->facility)
                ->where("role", "MTR")->count() > 0;

        //Has Ins Perms
        $data['isSupIns'] = $data['rating_short'] === "SUP" &&
            Role::where("facility", $data['facility'])
                ->where("cid", $user->cid)
                ->where("role", "INS")->exists();

        //Last Promotion
        $data['last_promotion'] = $user->lastPromotion() ? $user->lastPromotion()->created_at : null;

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
     *     summary="Get users assigned to specific staff role.",
     *     description="Get users assigned to specific staff role",
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
     *     summary="Assign new role. [Auth]",
     *     description="Assign new role. Requires JWT or Session Cookie (required roles :: for FE, EC, WM:
    ATM, DATM; for MTR: TA; for all other roles: VATUSA STAFF)", produces={"application/json"},
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
     *         examples={"application/json":{"status"="OK","testing"=false}}
     *     )
     * )
     */
    public function postRole($cid, $facility, $role)
    {
        if (!Auth::check()) {
            return response()->api(generate_error("Unauthorized"), 401);
        }

        $facility = Facility::find($facility);
        if (!$facility || ($facility->active != 1 && $facility->id != "ZHQ" && $facility->id != "ZAE")) {
            return response()->api(generate_error("Facility not found or invalid"), 404);
        }

        $role = strtoupper($role);

        if (!RoleHelper::canModify(Auth::user(), $facility, $role)) {
            return response()->api(generate_error("Forbidden"), 403);
        }

        if (!isTest()) {
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

            log_action($cid, "Assigned to role $role for $facility->id by " . Auth::user()->fullname());
        }

        return response()->ok();
    }

    /**
     * @param $cid
     * @param $facility
     * @param $role
     *
     * @return array|string
     *
     * @SWG\Delete(
     *     path="/user/(cid)/roles/(facility)/(role)",
     *     summary="Delete role. [Auth]",
     *     description="Delete role. Requires JWT or Session Cookie (required role: for FE, EC, WM roles: ATM,
    DATM; for MTR roles: TA; for all other roles: VATUSA STAFF)", produces={"application/json"}, tags={"user", "role"},
     *     security={"jwt","session"},
     * @SWG\Parameter(name="cid", in="path", required=true, type="integer", description="CERT ID"),
     * @SWG\Parameter(name="facility", in="path", required=true, type="string", description="Facility IATA ID"),
     * @SWG\Parameter(name="role", in="path", required=true, type="string", description="Role"),
     * @SWG\Response(
     *         response="401",
     *         description="Unauthorized",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Unauthorized"}},
     *     ),
     * @SWG\Response(
     *         response="403",
     *         description="Forbidden",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Forbidden"}},
     *     ),
     * @SWG\Response(
     *         response="404",
     *         description="Not found, role may not be assigned",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Not found"}},
     *     ),
     * @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(ref="#/definitions/OK"),
     *         examples={"application/json":{"status"="OK","testing"=false}}
     *     )
     * )
     * @throws \Exception
     */
    public function deleteRole($cid, $facility, $role)
    {
        if (!Auth::check()) {
            return response()->api(generate_error("Unauthorized"), 401);
        }

        $role = strtoupper($role);

        $fac = Facility::find($facility);
        if (!$fac || ($fac->active != 1 && $fac->id != "ZHQ" && $fac->id != "ZAE")) {
            return response()->api(generate_error("Facility not found or invalid"), 404);
        }

        if (!RoleHelper::canModify(Auth::user(), $fac, $role)) {
            return response()->api(generate_error("Forbidden"), 403);
        }

        if (!RoleHelper::has($cid, $facility, $role)) {
            return response()->api(generate_error("Not found"), 404);
        }

        if (!isTest()) {
            Role::where('facility', $facility)->where('role', $role)->where('cid', $cid)->delete();

            if (in_array($role, ['ATM', 'DATM', 'TA', 'EC', 'FE', 'WM'])) {
                if (Role::where('facility', $facility)->where('role', $role)->count() === 0) {
                    if (!EmailHelper::isStaticForward("$facility-$role@vatusa.net")) {
                        EmailHelper::deleteForward("$facility-$role@vatusa.net");
                        $destination = "$facility-sstf@vatusa.net";
                        if ($role === "datm") {
                            $destination = "$facility-atm@vatusa.net";
                        }
                        if ($role === "atm") {
                            $destination = "vatusa2@vatusa.net";
                        }
                        EmailHelper::setForward("$facility-$role@vatusa.net", $destination);
                    }
                }
            }

            log_action($cid, "Removed from role $role for $fac->id by " . Auth::user()->fullname());
        }

        return response()->ok();
    }

    /**
     * @param $cid
     *
     * @return array|string
     *
     * @SWG\Post(
     *     path="/user/(cid)/transfer",
     *     summary="Submit transfer request. [Private]",
     *     description="Submit transfer request. CORS Restricted, Requires JWT or Session Cookie (self or VATUSA
    staff)", produces={"application/json"}, tags={"user","transfer"}, security={"jwt","session"},
     * @SWG\Parameter(name="cid", in="path", required=true, type="integer", description="CERT ID"),
     * @SWG\Parameter(name="facility", in="formData", required=true, type="string", description="Facility IATA
     *                                     ID"),
     * @SWG\Parameter(name="reason", in="formData", required=true, type="string", description="Reason for transfer
     *                                   request"),
     * @SWG\Response(
     *         response="400",
     *         description="Malformed request (missing field?)",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Malformed request"}},
     *     ),
     * @SWG\Response(
     *         response="401",
     *         description="Unauthorized",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Unauthorized"}},
     *     ),
     * @SWG\Response(
     *         response="403",
     *         description="Forbidden",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Forbidden"}},
     *     ),
     * @SWG\Response(
     *         response="404",
     *         description="Facility not found",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Not found"}},
     *     ),
     * @SWG\Response(
     *         response="409",
     *         description="There was a conflict, usually meaning the user has a pending transfer request or is not
     *         eligible",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Conflict"}},
     *     ),
     * @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(ref="#/definitions/OK"),
     *         examples={"application/json":{"status"="OK","testing"=false}}
     *     )
     * )
     */
    public function postTransfer($cid)
    {
        if (!Auth::check()) {
            return response()->api(generate_error("Unauthorized"), 401);
        }
        if (Auth::user()->cid != $cid && !RoleHelper::isVATUSAStaff(Auth::user()->cid)) {
            return response()->api(generate_error("Forbidden"), 403);
        }
        $user = User::find($cid);
        if (!$user) {
            return response()->api(generate_error("Not found"), 404);
        }
        if (!$user->transferEligible()) {
            return response()->api(generate_error("Conflict"), 409);
        }

        $facility = request()->get("facility", null);
        $reason = request()->get("reason", null);
        if (!$facility || !Facility::find()) {
            return response()->api(generate_error("Not found"), 404);
        }
        if (strlen($reason) < 3) {
            return response()->api(generate_error("Malformed request"), 400);
        }

        if (!isTest()) {
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
            if ($transfer->to != "ZAE" && $transfer->to != "ZHQ") {
                $emails[] = $transfer->to . "-sstf@vatusa.net";
                $emails[] = "vatusa2@vatusa.net";
            }
            if ($transfer->from != "ZAE" && $transfer->from != "ZHQ") {
                $emails[] = $transfer->to . "-sstf@vatusa.net";
                $emails[] = "vatusa2@vatusa.net";
            }

            \Mail::to($emails)->send(new \App\Mail\TransferRequested($transfer));
        }

        return response()->ok();
    }

    /**
     * @param $cid
     *
     * @return array|string
     *
     * @SWG\Get(
     *     path="/user/(cid)/transfer/checklist",
     *     summary="Get user's transfer checklist. [Key]",
     *     description="Get user's checklist. Requires JWT, API Key, or Session Cookie (required role [N/A for
    apikey]: ATM, DATM, WM)", produces={"application/json"}, tags={"user","transfer"},
     *     security={"jwt","session","apikey"},
     * @SWG\Parameter(name="cid", in="path", required=true, type="integer", description="CERT ID"),
     * @SWG\Response(
     *         response="401",
     *         description="Unauthorized",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Unauthorized"}},
     *     ),
     * @SWG\Response(
     *         response="403",
     *         description="Forbidden",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Forbidden"}},
     *     ),
     * @SWG\Response(
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
        if (!$hasValidApiKey && !Auth::check()) {
            return response()->api(generate_error("Unauthorized"), 401);
        }
        if ($hasValidApiKey || (Auth::check() &&
                (
                    Auth::user()->cid == $cid ||
                    RoleHelper::isVATUSAStaff(Auth::user()->cid) ||
                    RoleHelper::has(Auth::user()->cid, Auth::user()->facility, ["ATM", "DATM", "WM"])
                )
            )) {
            $check = [];
            $overall = User::find($cid)->transferEligible($check);

            return response()->api(array_merge($check, ['overall' => $overall]));
        }

        return response()->api(generate_error("Forbidden"), 403);
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param                          $cid
     *
     * @return array|string
     *
     * @SWG\Post(
     *     path="/user/(cid)/rating",
     *     summary="Submit rating change. [Auth]",
     *     description="Submit rating change. Requires JWT or Session Cookie (required role: ATM, DATM, TA, INS,
    VATUSA STAFF)",
     *     produces={"application/json"}, tags={"user","rating"}, security={"jwt","session"},
     * @SWG\Parameter(name="cid", in="path", required=true, type="integer", description="CERT ID"),
     * @SWG\Parameter(name="rating", in="formData", required=true, type="string", description="Rating to change
    rating to"),
     *     @SWG\Parameter(name="examDate", in="formData", type="string", description="Date of exam (format, YYYY-MM-DD)
    required for C1 and below"),
     *     @SWG\Parameter(name="examiner", in="formData", type="integer", description="CID of Examiner, if not provided
    or null will default to authenticated user, required for C1 and below"),
     *     @SWG\Parameter(name="position", in="formData", type="string", description="Position sat during exam,
    required for C1 and below"),
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
     *         examples={"application/json":{"status"="OK","testing"=false}}
     *     )
     * )
     */
    public function postRating(Request $request, $cid)
    {
        if (!Auth::check()) {
            return response()->api(generate_error("Unauthorized"), 401);
        }
        $user = User::find($cid);
        if (!$user) {
            return response()->api(generate_error("Not found"), 404);
        }
        $rating = $request->input("rating", null);
        if (!$rating) {
            return response()->api(generate_error("Malformed request"), 400);
        }

        $examDate = $request->input("examDate", null); // Will be checked when appropriate
        $examiner = $request->input("examiner", null); // Will be checked when appropriate
        $position = $request->input("position", null); // Will be checked when appropriate

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

            if (isTest()) {
                return response()->api(["status" => "OK"]);
            }

            Promotion::process($cid, \Auth::user()->cid, $rating);
            // remove MTR/INS on promote to I1/I3
            $role = new Role();
            $mtr_ins_query = $role->where("cid", $cid)
                ->where(function ($query) {
                    $query->where("role", "MTR")->orWhere("role", "INS");
                });

            $changeRatingReturn = CERTHelper::changeRating($cid, $rating, true);
            if ($mtr_ins_query->count()) {
                try {
                    $mtr_ins_query->delete();
                    log_action($this->cid, "MTR/INS role removed on promotion to I1/I3");
                } catch (\Exception $e) {
                    return response()->api(["status" => "Internal server error"], 500);
                }
            }

            if ($changeRatingReturn) {
                return response()->api(["status" => "OK"]);
            } else {
                return response()->api(["status" => "Internal server error"], 500);
            }
        }

        // OBS-C1 changes
        if (!RoleHelper::isVATUSAStaff() &&
            !RoleHelper::has(Auth::user()->cid, Auth::user()->facility, ["ATM", "DATM", "TA"]) &&
            !RoleHelper::isInstructor(Auth::user()->cid, $user->facility)) {

            return response()->api(generate_error("Forbidden"), 403);
        }
        if ($user->rating >= $rating || $user->rating + 1 != $rating) {
            return response()->api(generate_error("Conflict"), 409);
        }

        $validator = Validator::make($request->all(), [
            'examDate' => 'required|date_format:Y-m-d',
            'position' => 'required|max:11',
        ]);
        if ($validator->fails()) {
            return response()->api(generate_error("Malformed request"), 400);
        }

        $user->checkPromotionCriteria($trainingRecordStatus, $otsEvalStatus, $examPosition, $dateOfExam, $evalId);
        if (!$user->promotionEligible()/* || !(abs($trainingRecordStatus) == 1 && abs($otsEvalStatus) == 1)*/) {
            return response()->api(generate_error("Precondition failed"), 412);
        }

        if (isTest()) {
            return response()->ok();
        }


        Promotion::process($user->cid, Auth::user()->cid, $user->rating + 1, $user->rating, $examDate, $examiner,
            $position, $evalId);
        $changeRatingReturn = CERTHelper::changeRating($cid, $rating, true);
        if ($changeRatingReturn) {
            return response()->ok();
        } else {
            return response()->api(["status" => "Internal server error"], 500);
        }
    }

    /**
     * @param $cid
     *
     * @return array|string
     *
     * @SWG\Get(
     *     path="/user/(cid)/rating/history",
     *     summary="Get user's rating history. [Key]",
     *     description="Get user's rating history. Requires API Key, JWT or Session Cookie (required role if no apikey:
     *     ATM, DATM, TA, INS, VATUSA STAFF)", produces={"application/json"}, tags={"user","rating"},
     *     security={"jwt","session","apikey"},
     * @SWG\Parameter(name="cid", in="path", required=true, type="integer", description="CERT ID"),
     * @SWG\Response(
     *         response="401",
     *         description="Unauthorized",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Unauthorized"}},
     *     ),
     * @SWG\Response(
     *         response="403",
     *         description="Forbidden",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Forbidden"}},
     *     ),
     * @SWG\Response(
     *         response="404",
     *         description="Not found",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Not found"}},
     *     ),
     * @SWG\Response(
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
            return response()->api(generate_error("Not found"), 404);
        }
        if (!$hasValidApiKey && !Auth::check()) {
            return response()->api(generate_error("Unauthorized"), 401);
        }
        if (!$hasValidApiKey && !(Auth::check() &&
                (
                    Auth::user()->cid == $cid ||
                    RoleHelper::isVATUSAStaff(Auth::user()->cid) ||
                    RoleHelper::has(Auth::user()->cid, Auth::user()->facility, ["ATM", "DATM", "WM"])
                )
            )) {

            return response()->api(generate_error("Forbidden"), 403);
        }

        $history = Promotion::where('cid', $cid)->orderBy('created_at', 'desc')->get()->toArray();

        return response()->api($history);
    }

    /**
     * @param $cid
     *
     * @return array|string
     *
     * @SWG\Get(
     *     path="/user/(cid)/log",
     *     summary="Get controller's action log. [Private]",
     *     description="Get controller's action log. CORS Restricted. Requires JWT or Session Cookie (required
    role: ATM, DATM, VATUSA STAFF)", produces={"application/json"}, tags={"user"}, security={"jwt","session"},
     * @SWG\Parameter(name="cid", in="path", required=true, type="integer", description="CERT ID"),
     * @SWG\Parameter(name="entry", in="formData", required=true, type="string", description="Entry to log"),
     * @SWG\Response(
     *         response="401",
     *         description="Unauthorized",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Unauthorized"}},
     *     ),
     * @SWG\Response(
     *         response="403",
     *         description="Forbidden",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Forbidden"}},
     *     ),
     * @SWG\Response(
     *         response="404",
     *         description="Not found",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Not found"}},
     *     ),
     * @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(
     *             type="array",
     *             @SWG\Items(ref="#/definitions/Action"),
     *         ),
     *         examples={"application/json":{{"id": 579572,"to": 1394143,"log": "Joined division, facility set to ZAE
               by CERTSync","created_at": "2017-06-01T00:02:09+00:00"}}}
     *     )
     * )
     */
    public function getActionLog($cid)
    {
        if (!User::find($cid)) {
            return response()->api(generate_error("Not found"), 404);
        }
        if (!Auth::check()) {
            return response()->api(generate_error("Unauthorized"), 401);
        }
        if (Auth::user()->cid == $cid ||
            RoleHelper::isVATUSAStaff(Auth::user()->cid) ||
            RoleHelper::has(Auth::user()->cid, Auth::user()->facility, ["ATM", "DATM"])
        ) {

            return response()->api(generate_error("Forbidden"), 403);
        }

        $logs = Action::where("to", $cid)->get()->toArray();

        return response()->api($logs);
    }

    /**
     * @param $cid
     *
     * @return array|string
     *
     * @SWG\Post(
     *     path="/user/(cid)/log",
     *     summary="Submit entry to controller's action log. [Private]",
     *     description="Submit entry to controller's action log. CORS Restricted. Requires JWT or Session Cookie
    (required role: ATM, DATM, VATUSA STAFF)", produces={"application/json"}, tags={"user"},
     *     security={"jwt","session"},
     * @SWG\Parameter(name="cid", in="path", required=true, type="integer", description="CERT ID"),
     * @SWG\Parameter(name="entry", in="formData", required=true, type="string", description="Entry to log"),
     * @SWG\Response(
     *         response="400",
     *         description="Malformed request",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Malformed request"}},
     *     ),
     * @SWG\Response(
     *         response="401",
     *         description="Unauthorized",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Unauthorized"}},
     *     ),
     * @SWG\Response(
     *         response="403",
     *         description="Forbidden",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Forbidden"}},
     *     ),
     * @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(ref="#/definitions/OK"),
     *         examples={"application/json":{"status"="OK","testing"=false}}
     *     )
     * )
     */
    public function postActionLog($cid)
    {
        $entry = request()->input("entry", null);
        if (!$entry) {
            return response()->api(generate_error("Malformed request"), 400);
        }
        if (!Auth::check()) {
            return response()->api(generate_error("Unauthorized"), 401);
        }
        if (!RoleHelper::isVATUSAStaff(Auth::user()->cid) && !RoleHelper::has(Auth::user()->cid,
                Auth::user()->facility, ["ATM", "DATM"])) {
            return response()->api(generate_error("Forbidden"), 403);
        }

        log_action($cid, $entry);

        return response()->ok();
    }

    /**
     * @param $cid
     *
     * @return array|string
     *
     * @SWG\Get(
     *     path="/user/(cid)/transfer/history",
     *     summary="Get user's transfer history. [Key]",
     *     description="Get user's transfer history. Requires API Key, JWT or Session Cookie (required role: [N/A for
     *     API
    Key] ATM, DATM, TA, WM, VATUSA STAFF)", produces={"application/json"}, tags={"user","transfer"},
     *     security={"jwt","session","apikey"},
     * @SWG\Parameter(name="cid", in="path", required=true, type="integer", description="CERT ID"),
     * @SWG\Response(
     *         response="401",
     *         description="Unauthorized",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Unauthorized"}},
     *     ),
     * @SWG\Response(
     *         response="403",
     *         description="Forbidden",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Forbidden"}},
     *     ),
     * @SWG\Response(
     *         response="404",
     *         description="Not found",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Not found"}},
     *     ),
     * @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(
     *             type="array",
     *             @SWG\Items(
     *                 ref="#/definitions/Transfer"
     *             )
     *         ),
     *         examples={"application/json":{{"id":673608,"cid":1055319,"to":"ZAE","from":"ZNY","reason":"Removed for
               inactivity.","status":1,"actiontext":"Removed for
    inactivity.","actionby":0,"created_at":"2017-01-01T12:06:27+00:00","updated_at":"2017-01-01T12:06:27+00:00"}}},
     *     )
     * )
     */
    public function getTransferHistory($cid)
    {
        $hasValidApiKey = AuthHelper::validApiKeyv2(request()->input('apikey', null));

        if (!User::find($cid)) {
            return response()->api(generate_error("Not found"), 404);
        }
        if (!$hasValidApiKey && !Auth::check()) {
            return response()->api(generate_error("Unauthorized"), 401);
        }

        if (!$hasValidApiKey && !(Auth::check() &&
                (
                    Auth::user()->cid == $cid ||
                    RoleHelper::isVATUSAStaff(Auth::user()->cid) ||
                    RoleHelper::has(Auth::user()->cid, Auth::user()->facility, ["ATM", "DATM", "TA", "WM"])
                )
            )) {
            return response()->api(generate_error("Forbidden"), 403);
        }

        $transfers = Transfer::where('cid', $cid)->orderBy('created_at', 'desc')->get()->toArray();

        return response()->api($transfers);
    }

    /**
     * @param $partialCid
     *
     * @return array|string
     *
     * @SWG\Get(
     *     path="/user/filtercid/(partialCid)",
     *     summary="Filter users by partial CID.",
     *     description="Get an array of users matching a given partial CID.",
     *     produces={"application/json"}, tags={"user"},
     * @SWG\Parameter(name="partialCid", in="path", required=true, type="integer", description="Partial CERT ID"),
     * @SWG\Response(
     *         response="404",
     *         description="Not Found",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="No matching users found."}},
     *     ),
     * @SWG\Response(
     *         response="412",
     *         description="Precondition Failed (>= 4 digits)",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Partial CID must be at least 4 digits."}},
     *     ),
     * @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(
     *             type="array",
     *             @SWG\Items(
     *                 type="object",
     *                 @SWG\Property(property="cid", type="integer"),
     *                 @SWG\Property(property="fname", type="string"),
     *                 @SWG\Property(property="lname", type="string"),
     *             )
     *         ),
     *         examples={"application/json":{"0":{"cid":1391803,"fname":"Michael","lname":"Romashov"},"1":{"cid":1391802,"fname":"Sankara","lname":"Narayanan "}}}
     *     )
     * )
     */
    public function filterUsersCid($partialCid)
    {
        if (strlen($partialCid) < 4) {
            return response()->api(generate_error("Partial CID must be at least 4 digits."), 412);
        }

        $matches = User::where('cid', 'like', "%$partialCid%")->orderBy('fname', 'asc')->get();

        if (!$matches) {
            return response()->api(generate_error("No matching users found.", 404));
        }

        $return = [];
        foreach ($matches as $match) {
            $return[] = ['cid' => $match->cid, 'fname' => $match->fname, 'lname' => $match->lname];
        }

        return response()->api($return);
    }

    /**
     * @param $partialLName
     *
     * @return array|string
     *
     * @SWG\Get(
     *     path="/user/filterlname/(partialLName)",
     *     summary="Filter users by partial last name.",
     *     description="Get an array of users matching a given partial last name.",
     *     produces={"application/json"}, tags={"user"},
     * @SWG\Parameter(name="partialLName", in="path", required=true, type="string", description="Partial Last Name"),
     * @SWG\Response(
     *         response="404",
     *         description="Not Found",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="No matching users found."}},
     *     ),
     * @SWG\Response(
     *         response="412",
     *         description="Precondition Failed (>= 4 letters)",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Partial last name must be at least 4 letters."}},
     *     ),
     * @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(
     *             type="array",
     *             @SWG\Items(
     *                 type="object",
     *                 @SWG\Property(property="cid", type="integer"),
     *                 @SWG\Property(property="fname", type="string"),
     *                 @SWG\Property(property="lname", type="string"),
     *             )
     *         ),
     *         examples={"application/json":{"0":{"cid":1459055,"fname":"Aidan","lname":"Deschene"},"1":{"cid":1263769,"fname":"Austin","lname":"Tedesco"},"2":{"cid":919571,"fname":"Matthew","lname":"Tedesco"},"3":{"cid":1202101,"fname":"Mike","lname":"Tedesco"}}}
     *     )
     * )
     */
    public function filterUsersLName($partialLName)
    {
        if (strlen($partialLName) < 4) {
            return response()->api(generate_error("Partial last name must be at least 4 letters."), 412);
        }

        $matches = User::where('lname', 'like', "%$partialLName%")->orderBy('fname', 'asc')->get();

        if (!$matches) {
            return response()->api(generate_error("No matching users found.", 404));
        }

        $return = [];
        foreach ($matches as $match) {
            $return[] = ['cid' => $match->cid, 'fname' => $match->fname, 'lname' => $match->lname];
        }

        return response()->api($return);
    }
}
