<?php

namespace App\Http\Controllers\API\v2;

use App\Action;
use App\Helpers\AuthHelper;
use App\Helpers\EmailHelper;
use App\Helpers\Helper;
use App\Helpers\RatingHelper;
use App\Helpers\RoleHelper;
use App\Helpers\VATSIMApi2Helper;
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
     * @OA\Get(
     *     path="/user/(cid)",
     *     summary="Get user's information.",
     *     description="Get user's information. Email field, broadcast opt-in status, and visiting facilities require authentication as staff member or API key.
    Prevent staff assigment flag requires authentication as senior staff.",
     *      tags={"user"},
     * @OA\Parameter(name="cid",in="path",required=true,@OA\Schema(type="string"),description="Cert ID"),
     * @OA\Response(
     *         response="404",
     *         description="Not found",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     * @OA\Response(
     *         response="200",
     *         description="OK",
     *         @OA\Schema(ref="#/components/schemas/User")
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
        $data['facility_join'] = Carbon::createFromFormat('Y-m-d H:i:s', $user->facility_join)->format('c');
        $data['lastactivity'] = Carbon::createFromFormat('Y-m-d H:i:s', $user->lastactivity)->format('c');

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
     * @OA\Get(
     *     path="/user/roles/(facility)/(role)",
     *     summary="Get users assigned to specific staff role.",
     *     description="Get users assigned to specific staff role",
     *     tags={"user","role"},
     *     @OA\Parameter(name="facility", in="path", required=true, @OA\Schema(type="string"), description="Facility IATA ID"),
     *     @OA\Parameter(name="role", in="path", required=true, @OA\Schema(type="string"), description="Role"),
     *     @OA\Response(
     *         response="200",
     *         description="OK",
     *         @OA\Schema(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="cid",type="integer",description="CERT ID of user"),
     *                 @OA\Property(property="lname",type="string",description="Last name"),
     *                 @OA\Property(property="fname",type="string",description="First name"),
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
     * @OA\Post(
     *     path="/user/(cid)/roles/(facility)/(role)",
     *     summary="Assign new role. [Auth]",
     *     description="Assign new role. Requires JWT or Session Cookie (required roles :: for FE, EC, WM:
    ATM, DATM; for MTR: TA; for all other roles: VATUSA STAFF)", 
     *     tags={"user","role"}, security={"jwt","session"},
     *     @OA\Parameter(name="cid", in="path", required=true, @OA\Schema(type="integer"), description="CERT ID"),
     *     @OA\Parameter(name="facility", in="path", required=true, @OA\Schema(type="string"), description="Facility IATA ID"),
     *     @OA\Parameter(name="role", in="path", required=true, @OA\Schema(type="string"), description="Role"),
     *     @OA\Response(
     *         response="401",
     *         description="Unauthorized",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Forbidden",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="OK",
     *         @OA\Schema(ref="#/components/schemas/OK"),
     *         
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
     * @OA\Delete(
     *     path="/user/(cid)/roles/(facility)/(role)",
     *     summary="Delete role. [Auth]",
     *     description="Delete role. Requires JWT or Session Cookie (required role: for FE, EC, WM roles: ATM,
    DATM; for MTR roles: TA; for all other roles: VATUSA STAFF)",  tags={"user", "role"},
     *     security={"jwt","session"},
     * @OA\Parameter(name="cid", in="path", required=true, @OA\Schema(type="integer"), description="CERT ID"),
     * @OA\Parameter(name="facility", in="path", required=true, @OA\Schema(type="string"), description="Facility IATA ID"),
     * @OA\Parameter(name="role", in="path", required=true, @OA\Schema(type="string"), description="Role"),
     * @OA\Response(
     *         response="401",
     *         description="Unauthorized",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     * @OA\Response(
     *         response="403",
     *         description="Forbidden",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     * @OA\Response(
     *         response="404",
     *         description="Not found, role may not be assigned",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     * @OA\Response(
     *         response="200",
     *         description="OK",
     *         @OA\Schema(ref="#/components/schemas/OK"),
     *         
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
     * @OA\Post(
     *     path="/user/(cid)/transfer",
     *     summary="Submit transfer request. [Private]",
     *     description="Submit transfer request. CORS Restricted, Requires JWT or Session Cookie (self or VATUSA
    staff)",  tags={"user","transfer"}, security={"jwt","session"},
     * @OA\Parameter(name="cid", in="path", required=true, @OA\Schema(type="integer"), description="CERT ID"),
     * @OA\RequestBody(@OA\MediaType(mediaType="application/x-www-form-urlencoded",@OA\Schema(
     * @OA\Parameter(name="facility", required=true, @OA\Schema(type="string"), description="Facility IATA
     *                                     ID"),
     * @OA\Parameter(name="reason", required=true, @OA\Schema(type="string"), description="Reason for transfer
     *                                   request"),
     * ))),
     * @OA\Response(
     *         response="400",
     *         description="Malformed request (missing field?)",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     * @OA\Response(
     *         response="401",
     *         description="Unauthorized",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     * @OA\Response(
     *         response="403",
     *         description="Forbidden",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     * @OA\Response(
     *         response="404",
     *         description="Facility not found",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     * @OA\Response(
     *         response="409",
     *         description="There was a conflict, usually meaning the user has a pending transfer request or is not
     *         eligible",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     * @OA\Response(
     *         response="200",
     *         description="OK",
     *         @OA\Schema(ref="#/components/schemas/OK"),
     *         
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
     * @OA\Get(
     *     path="/user/(cid)/transfer/checklist",
     *     summary="Get user's transfer checklist. [Key]",
     *     description="Get user's checklist. Requires JWT, API Key, or Session Cookie (required role [N/A for
    apikey]: ATM, DATM, WM)",  tags={"user","transfer"},
     *     security={"jwt","session","apikey"},
     * @OA\Parameter(name="cid", in="path", required=true, @OA\Schema(type="integer"), description="CERT ID"),
     * @OA\Response(
     *         response="401",
     *         description="Unauthorized",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     * @OA\Response(
     *         response="403",
     *         description="Forbidden",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     * @OA\Response(
     *         response="200",
     *         description="OK",
     *         @OA\Schema(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="item", @OA\Schema(type="string"), description="Checklist checked item"),
     *                 @OA\Property(property="result", @OA\Schema(type="string"), description="Result of check (OK, FAIL)"),
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
     * @OA\Post(
     *     path="/user/(cid)/rating",
     *     summary="Submit rating change. [Auth]",
     *     description="Submit rating change. Requires JWT or Session Cookie (required role: ATM, DATM, TA, INS,
    VATUSA STAFF)",
     *      tags={"user","rating"}, security={"jwt","session"},
     * @OA\Parameter(name="cid", in="path", required=true, @OA\Schema(type="integer"), description="CERT ID"),
     * @OA\RequestBody(@OA\MediaType(mediaType="application/x-www-form-urlencoded",@OA\Schema(
     * @OA\Parameter(name="rating", required=true, @OA\Schema(type="string"), description="Rating to change
    rating to"),
     *     @OA\Parameter(name="examDate", @OA\Schema(type="string"), description="Date of exam (format, YYYY-MM-DD)
    required for C1 and below"),
     *     @OA\Parameter(name="examiner", @OA\Schema(type="integer"), description="CID of Examiner, if not provided
    or null will default to authenticated user, required for C1 and below"),
     *     @OA\Parameter(name="position", @OA\Schema(type="string"), description="Position sat during exam,
    required for C1 and below"),
     * ))),
     *     @OA\Response(
     *         response="401",
     *         description="Unauthorized",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Forbidden",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Not found",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     *     @OA\Response(
     *         response="409",
     *         description="Conflict, when current rating and promoted rating are the same or demotion not possible",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     *     @OA\Response(
     *         response="412",
     *         description="Precondition failed (not eligible)",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     *     @OA\Response(
     *         response="500",
     *         description="CERT error, contact data services team",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="OK",
     *         @OA\Schema(ref="#/components/schemas/OK"),
     *         
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
            // remove MTR on promote to I1/I3
            $role = new Role();
            $mtr_query = $role->where("cid", $cid)
                ->where(function ($query) {
                    $query->where("role", "MTR");
                });

            $changeRatingReturn = VATSIMApi2Helper::updateRating($cid, $rating);
            if ($mtr_query->count()) {
                try {
                    $mtr_query->delete();
                    log_action($this->cid, "MTR role removed on promotion to I1/I3");
                } catch (\Exception $e) {
                    return response()->api(["status" => "Internal server error"], 500);
                }
            }

            if ($changeRatingReturn) {
                $user->rating = $rating;
                $user->save();
                return response()->api(["status" => "OK"]);
            } else {
                return response()->api(["status" => "Internal server error"], 500);
            }
        }

        // OBS-C1 changes
        if (!RoleHelper::isVATUSAStaff() &&
            !RoleHelper::has(Auth::user()->cid, Auth::user()->facility, ["ATM", "DATM", "TA"]) &&
            !RoleHelper::isInstructor(Auth::user()->cid, $user->facility) &&
            !(RoleHelper::isMentor(Auth::user()->cid, $user->facility) && Auth::user()->rating>=4 && $rating==2)) {

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
        $changeRatingReturn = VATSIMApi2Helper::updateRating($cid, $rating);
        if ($changeRatingReturn) {
            $user->rating = $rating;
            $user->save();
            $user->promotionEligible(); // reset cache after promotion
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
     * @OA\Get(
     *     path="/user/(cid)/rating/history",
     *     summary="Get user's rating history. [Key]",
     *     description="Get user's rating history. Requires API Key, JWT or Session Cookie (required role if no apikey:
     *     ATM, DATM, TA, INS, VATUSA STAFF)",  tags={"user","rating"},
     *     security={"jwt","session","apikey"},
     * @OA\Parameter(name="cid", in="path", required=true, @OA\Schema(type="integer"), description="CERT ID"),
     * @OA\Response(
     *         response="401",
     *         description="Unauthorized",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     * @OA\Response(
     *         response="403",
     *         description="Forbidden",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     * @OA\Response(
     *         response="404",
     *         description="Not found",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     * @OA\Response(
     *         response="200",
     *         description="OK",
     *         @OA\Schema(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/Promotion"),
     *         ),
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
     * @OA\Get(
     *     path="/user/(cid)/log",
     *     summary="Get controller's action log. [Private]",
     *     description="Get controller's action log. CORS Restricted. Requires JWT or Session Cookie (required
    role: ATM, DATM, VATUSA STAFF)",  tags={"user"}, security={"jwt","session"},
     * @OA\Parameter(name="cid", in="path", required=true, @OA\Schema(type="integer"), description="CERT ID"),
     * @OA\RequestBody(@OA\MediaType(mediaType="application/x-www-form-urlencoded",@OA\Schema(
     * @OA\Parameter(name="entry", required=true, @OA\Schema(type="string"), description="Entry to log"),
     * ))),
     * @OA\Response(
     *         response="401",
     *         description="Unauthorized",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     * @OA\Response(
     *         response="403",
     *         description="Forbidden",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     * @OA\Response(
     *         response="404",
     *         description="Not found",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     * @OA\Response(
     *         response="200",
     *         description="OK",
     *         @OA\Schema(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/Action"),
     *         ),
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
     * @OA\Post(
     *     path="/user/(cid)/log",
     *     summary="Submit entry to controller's action log. [Private]",
     *     description="Submit entry to controller's action log. CORS Restricted. Requires JWT or Session Cookie
    (required role: ATM, DATM, VATUSA STAFF)",  tags={"user"},
     *     security={"jwt","session"},
     * @OA\Parameter(name="cid", in="path", required=true, @OA\Schema(type="integer"), description="CERT ID"),
     * @OA\RequestBody(@OA\MediaType(mediaType="application/x-www-form-urlencoded",@OA\Schema(
     * @OA\Parameter(name="entry", required=true, @OA\Schema(type="string"), description="Entry to log"),
     * ))),
     * @OA\Response(
     *         response="400",
     *         description="Malformed request",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     * @OA\Response(
     *         response="401",
     *         description="Unauthorized",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     * @OA\Response(
     *         response="403",
     *         description="Forbidden",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     * @OA\Response(
     *         response="200",
     *         description="OK",
     *         @OA\Schema(ref="#/components/schemas/OK"),
     *         
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
     * @OA\Get(
     *     path="/user/(cid)/transfer/history",
     *     summary="Get user's transfer history. [Key]",
     *     description="Get user's transfer history. Requires API Key, JWT or Session Cookie (required role: [N/A for
     *     API
    Key] ATM, DATM, TA, WM, VATUSA STAFF)",  tags={"user","transfer"},
     *     security={"jwt","session","apikey"},
     * @OA\Parameter(name="cid", in="path", required=true, @OA\Schema(type="integer"), description="CERT ID"),
     * @OA\Response(
     *         response="401",
     *         description="Unauthorized",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     * @OA\Response(
     *         response="403",
     *         description="Forbidden",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     * @OA\Response(
     *         response="404",
     *         description="Not found",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     * @OA\Response(
     *         response="200",
     *         description="OK",
     *         @OA\Schema(
     *             type="array",
     *             @OA\Items(
     *                 ref="#/components/schemas/Transfer"
     *             )
     *         ),
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
     * @OA\Get(
     *     path="/user/filtercid/(partialCid)",
     *     summary="Filter users by partial CID.",
     *     description="Get an array of users matching a given partial CID.",
     *      tags={"user"},
     * @OA\Parameter(name="partialCid", in="path", required=true, @OA\Schema(type="integer"), description="Partial CERT ID"),
     * @OA\Response(
     *         response="404",
     *         description="Not Found",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     * @OA\Response(
     *         response="412",
     *         description="Precondition Failed (>= 4 digits)",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     * @OA\Response(
     *         response="200",
     *         description="OK",
     *         @OA\Schema(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="cid", @OA\Schema(type="integer")),
     *                 @OA\Property(property="fname", @OA\Schema(type="string")),
     *                 @OA\Property(property="lname", @OA\Schema(type="string")),
     *             )
     *         ),
     *         
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
     * @OA\Get(
     *     path="/user/filterlname/(partialLName)",
     *     summary="Filter users by partial last name.",
     *     description="Get an array of users matching a given partial last name.",
     *      tags={"user"},
     * @OA\Parameter(name="partialLName", in="path", required=true, @OA\Schema(type="string"), description="Partial Last Name"),
     * @OA\Response(
     *         response="404",
     *         description="Not Found",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     * @OA\Response(
     *         response="412",
     *         description="Precondition Failed (>= 4 letters)",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     * @OA\Response(
     *         response="200",
     *         description="OK",
     *         @OA\Schema(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="cid", @OA\Schema(type="integer")),
     *                 @OA\Property(property="fname", @OA\Schema(type="string")),
     *                 @OA\Property(property="lname", @OA\Schema(type="string")),
     *             )
     *         ),
     *         
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
