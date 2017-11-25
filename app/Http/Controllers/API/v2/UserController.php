<?php

namespace App\Http\Controllers\API\v2;

use App\Helpers\AuthHelper;
use App\Helpers\EmailHelper;
use App\Helpers\RatingHelper;
use App\Helpers\RoleHelper;
use App\Role;
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
     * @param string $facility
     * @param string $role
     * @return array|string
     *
     * @TODO
     *
     * @SWG\Get(
     *     path="/user/roles/(facility)/(role)",
     *     summary="Get users assigned to specific role",
     *     description="Get users assigned to specific role",
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
     *                 @SWG\Property(property="lastname",type="string",description="Last name"),
     *                 @SWG\Property(property="firstname",type="string",description="First name"),
     *             ),
     *         ),
     *     )
     * ),
     */
    public function getRoleUsers($facility, $role) {

    }
    /**
     * @param int $cid
     * @param string $facility
     * @param string $role
     * @return array|string
     *
     * @TODO Add role, add action log entry
     *
     * @SWG\Put(
     *     path="/user/(cid)/roles/(facility)/(role)",
     *     summary="Assign new role. Requires JWT or Session Cookie",
     *     description="Assign new role. Requires JWT or Session Cookie (required role: for FE, EC, WM roles: ATM, DATM, for MTR roles: TA, for all other roles: VATUSA STAFF)",
     *     produces={"application/json"},
     *     tags={"user","role"},
     *     security={"jwt","session"},
     *     @SWG\Parameter(name="cid", in="path", required=true, type="integer", description="CERT ID"),
     *     @SWG\Parameter(name="facility", in="path", required=true, type="string", description="Facility IATA ID"),
     *     @SWG\Parameter(name="role", in="path", required=true, type="string", description="Role"),
     *     @SWG\Response(
     *         response="401",
     *         description="Unauthenticated",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Unauthenticated"}},
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
    public function putRole($cid, $facility, $role) {
        $facility = Facility::find($facility);
        if (!$facility || ($facility->active != 1 && $facility->id != "ZHQ" && $facility->id != "ZAE")) {
            return response()->api(generate_error("Facility not found or invalid"), 404);
        }

        if (!RoleHelper::canModify(\Auth::user(), $facility, $role)) {
            return response()->api(generate_error("Forbidden"), 403);
        }

        if (in_array($role, ['ATM','DATM','TA','EC','FE','WM'])) {
            if (Role::where("facility", $facility->id)->where("role", $role)->count() == 0) {
                // New person, setup the forward
                $email = strtolower($facility->id . "-" . $role . "@vatusa.net");
                if (!EmailHelper::deleteForward($email)) {
                    \Log::critical("Couldn't delete forward for $email");
                }
            }
        }
    }
    /**
     * @return array|string
     *
     * @TODO add action log entry
     *
     * @SWG\Delete(
     *     path="/user/(cid)/roles/(facility)/(role)",
     *     summary="Delete role. Requires JWT or Session Cookie",
     *     description="Delete role. Requires JWT or Session Cookie (required role: for FE, EC, WM roles: ATM, DATM, for MTR roles: TA, for all other roles: VATUSA STAFF)",
     *     produces={"application/json"},
     *     tags={"user", "role"},
     *     security={"jwt","session"},
     *     @SWG\Parameter(name="cid", in="path", required=true, type="integer", description="CERT ID"),
     *     @SWG\Parameter(name="facility", in="path", required=true, type="string", description="Facility IATA ID"),
     *     @SWG\Parameter(name="role", in="path", required=true, type="string", description="Role"),
     *     @SWG\Response(
     *         response="401",
     *         description="Unauthenticated",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Unauthenticated"}},
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
    public function deleteRole($cid, $facility, $role) {
        $role = strtolower($role);

        $fac = Facility::find($facility);
        if (!$fac || ($fac->active != 1 && $fac->id != "ZHQ" && $fac->id != "ZAE")) {
            return response()->json(generate_error("Facility not found or invalid"), 404);
        }

        if (!RoleHelper::canModify(\Auth::user(), $facility, $role)) {
            return response()->json(generate_error("Forbidden"), 403);
        }

        if (!RoleHelper::has($cid, $facility, $role)) {
            return response()->json(generate_error("Not found"), 404);
        }

        Role::where('facility', $facility)->where('role', $role)->where('cid', $cid)->delete();

        if (in_array($role, ['atm','datm','ta','ec','fe','wm'])) {
            if (Role::where('facility', $facility)->where('role', $role)->count() === 0) {
                EmailHelper::deleteForward("$facility-$role@vatusa.net");
                $destination = "$facility-sstaf@vatusa.net";
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
    /**
     * @return array|string
     *
     * @TODO
     *
     * @SWG\Put(
     *     path="/user/(cid)/transfer",
     *     summary="Submit transfer request. Requires JWT or Session Cookie",
     *     description="Submit transfer request. Requires JWT or Session Cookie (self or VATUSA staff)",
     *     produces={"application/json"},
     *     tags={"user","transfer"},
     *     security={"jwt","session"},
     *     @SWG\Parameter(name="cid", in="path", required=true, type="integer", description="CERT ID"),
     *     @SWG\Parameter(name="facility", in="formData", required=true, type="string", description="Facility IATA ID"),
     *     @SWG\Parameter(name="reason", in="formData", required=true, type="string", description="Reason for transfer request"),
     *     @SWG\Response(
     *         response="401",
     *         description="Unauthenticated",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Unauthenticated"}},
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Forbidden",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Forbidden"}},
     *     ),
     *     @SWG\Response(
     *         response="409",
     *         description="There was a conflict, usually meaning the user has a pending transfer request",
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
    public function putTransfer($cid) {

    }
    /**
     * @return array|string
     *
     * @TODO
     *
     * @SWG\Get(
     *     path="/user/(cid)/transfer/checklist",
     *     summary="Get user's transfer checklist. Requires JWT, API Key, or Session Cookie",
     *     description="Get user's checklist. Requires JWT, API Key, or Session Cookie (required role [N/A for apikey]: ATM, DATM, WM)",
     *     produces={"application/json"},
     *     tags={"user","transfer"},
     *     security={"jwt","session","apikey"},
     *     @SWG\Parameter(name="cid", in="path", required=true, type="integer", description="CERT ID"),
     *     @SWG\Response(
     *         response="401",
     *         description="Unauthenticated",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Unauthenticated"}},
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
     *                 @SWG\Property(property="other", type="string", description="Misc info for checked item (ie, number of days since last transfer for 90 day check)")
     *             )
     *         ),
     *     )
     * )
     */
    public function getTransferChecklist($cid) {

    }
    /**
     * @return array|string
     *
     * @TODO
     *
     * @SWG\Post(
     *     path="/user/(cid)/rating",
     *     summary="Submit rating change. Requires JWT or Session Cookie",
     *     description="Submit rating change. Requires JWT or Session Cookie (required role: ATM, DATM, TA, INS, VATUSA STAFF)",
     *     produces={"application/json"},
     *     tags={"user","rating"},
     *     security={"jwt","session"},
     *     @SWG\Parameter(name="cid", in="path", required=true, type="integer", description="CERT ID"),
     *     @SWG\Parameter(name="rating", in="formData", required=true, type="string", description="Rating to change rating to"),
     *     @SWG\Parameter(name="examDate", in="formData", type="string", description="Date of exam (format, YYYY-MM-DD) required for C1 and below"),
     *     @SWG\Parameter(name="examiner", in="formData", type="integer", description="CID of Examiner, if not provided or null will default to authenticated user, required for C1 and below"),
     *     @SWG\Parameter(name="position", in="formData", type="string", description="Position sat during exam, required for C1 and below"),
     *     @SWG\Response(
     *         response="401",
     *         description="Unauthenticated",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Unauthenticated"}},
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Forbidden",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Forbidden"}},
     *     ),
     *     @SWG\Response(
     *         response="409",
     *         description="Conflict, when current rating and promoted rating are the same",
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
    public function postRating($cid) {

    }

    /**
     * @return array|string
     *
     * @TODO
     *
     * @SWG\Get(
     *     path="/user/(cid)/rating/history",
     *     summary="Get user's rating history. Requires JWT, API Key or Session Cookie",
     *     description="Get user's rating history. Requires JWT, API Key or Session Cookie (required role: [N/A for API Key] ATM, DATM, TA, INS, VATUSA STAFF)",
     *     produces={"application/json"},
     *     tags={"user","rating"},
     *     security={"jwt","session","apikey"},
     *     @SWG\Parameter(name="cid", in="path", required=true, type="integer", description="CERT ID"),
     *     @SWG\Response(
     *         response="401",
     *         description="Unauthenticated",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Unauthenticated"}},
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
     *                 @SWG\Property(property="id", type="integer", description="Promotion ID Number"),
     *                 @SWG\Property(property="date", type="string", description="Date of Transfer (YYYY-MM-DD)"),
     *                 @SWG\Property(property="ratingTo", type="string", description="Rating given (S1, S2, etc)"),
     *                 @SWG\Property(property="ratingFrom", type="string", description="Previous rating (S1, S2, etc)"),
     *             )
     *         ),
     *     )
     * )
     */
    public function getRatingHistory($cid) {

    }

    /**
     * @return array|string
     *
     * @TODO
     *
     * @SWG\Put(
     *     path="/user/(cid)/log",
     *     summary="Submit entry to controller's action log. Requires JWT or Session Cookie",
     *     description="Submit entry to controller's action log. Requires JWT or Session Cookie (required role: ATM, DATM, VATUSA STAFF)",
     *     produces={"application/json"},
     *     tags={"user"},
     *     security={"jwt","session"},
     *     @SWG\Parameter(name="cid", in="path", required=true, type="integer", description="CERT ID"),
     *     @SWG\Parameter(name="entry", in="formData", required=true, type="string", description="Entry to log"),
     *     @SWG\Response(
     *         response="401",
     *         description="Unauthenticated",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Unauthenticated"}},
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
    public function putActionLog($cid) {

    }

    /**
     * @return array|string
     *
     * @TODO
     *
     * @SWG\Get(
     *     path="/user/(cid)/transfer/history",
     *     summary="Get user's transfer history. Requires JWT or Session Cookie",
     *     description="Get user's history. Requires JWT or Session Cookie (required role: [N/A for API Key] ATM, DATM, TA, INS, VATUSA STAFF)",
     *     produces={"application/json"},
     *     tags={"user","transfer"},
     *     security={"jwt","session","apikey"},
     *     @SWG\Parameter(name="cid", in="path", required=true, type="integer", description="CERT ID"),
     *     @SWG\Response(
     *         response="401",
     *         description="Unauthenticated",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Unauthenticated"}},
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
     *                 @SWG\Property(property="id", type="integer", description="Transfer ID Number"),
     *                 @SWG\Property(property="date", type="string", description="Date of Transfer (YYYY-MM-DD)"),
     *                 @SWG\Property(property="facilityTo", type="string", description="Facility IATA ID request was addressed to"),
     *                 @SWG\Property(property="facilityFrom", type="string", description="Facility IATA ID user was in"),
     *                 @SWG\Property(property="status", type="string", description="Status of request (pending, approved, rejected)")
     *             )
     *         ),
     *     )
     * )
     */
    public function getTransferHistory($cid) {

    }

    /**
     * @return array|string
     *
     * @TODO
     *
     * @SWG\Get(
     *     path="/user/(cid)/cbt/history",
     *     summary="Get user's CBT history. Requires JWT, API Key or Session Cookie",
     *     description="Get user's CBT history. Requires JWT, API Key or Session Cookie (required role: [N/A for API Key] ATM, DATM, TA, INS, VATUSA STAFF)",
     *     produces={"application/json"},
     *     tags={"user","cbt"},
     *     security={"jwt","session","apikey"},
     *     @SWG\Parameter(name="cid", in="path", required=true, type="integer", description="CERT ID"),
     *     @SWG\Parameter(name="completedOnly", in="query", type="boolean", description="Display only completed CBT Blocks"),
     *     @SWG\Parameter(name="facility", in="query", type="string", description="Filter for facility IATA ID"),
     *     @SWG\Parameter(name="blockId", in="query", type="integer", description="Lookup progress of specific Block ID"),
     *     @SWG\Response(
     *         response="401",
     *         description="Unauthenticated",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Unauthenticated"}},
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
     *                 @SWG\Property(property="id", type="integer", description="Block ID Number"),
     *                 @SWG\Property(property="facility", type="string", description="Block's owning facility"),
     *                 @SWG\Property(property="blockName", type="string", description="Name of block"),
     *                 @SWG\Property(property="completed", type="boolean"),
     *             )
     *         ),
     *     )
     * )
     */
    public function getCBTProgress($cid) {

    }

    /**
     * @return array|string
     *
     * @TODO
     *
     * @SWG\Put(
     *     path="/user/(cid)/cbt/progress/(blockId)",
     *     summary="Get user's CBT history. Requires JWT, API Key or Session Cookie",
     *     description="Get user's CBT history. Requires JWT, API Key or Session Cookie (required role: [N/A for API Key] ATM, DATM, TA, INS, VATUSA STAFF)",
     *     produces={"application/json"},
     *     tags={"user","cbt"},
     *     security={"jwt","session","apikey"},
     *     @SWG\Parameter(name="cid", in="path", required=true, type="integer", description="CERT ID"),
     *     @SWG\Parameter(name="blockId", in="query", type="integer", description="Mark progress of specific Block ID"),
     *     @SWG\Response(
     *         response="401",
     *         description="Unauthenticated",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Unauthenticated"}},
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
    public function putCBTProgress($cid, $blockId) {

    }

    /**
     * @return array|string
     *
     * @TODO
     *
     * @SWG\Get(
     *     path="/user/(cid)/exam/history",
     *     summary="Get user's exam history. Requires JWT, API Key or Session Cookie",
     *     description="Get user's exam history. Requires JWT, API Key or Session Cookie (required role: [N/A for API Key] ATM, DATM, TA, INS, VATUSA STAFF)",
     *     produces={"application/json"},
     *     tags={"user","exam"},
     *     security={"jwt","session","apikey"},
     *     @SWG\Parameter(name="cid", in="path", required=true, type="integer", description="CERT ID"),
     *     @SWG\Response(
     *         response="401",
     *         description="Unauthenticated",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Unauthenticated"}},
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
     *                 @SWG\Property(property="id", type="integer", description="Exam Result ID Number"),
     *                 @SWG\Property(property="date", type="string", description="Date of Exam (YYYY-MM-DD)"),
     *                 @SWG\Property(property="examName", type="string", description="Name of exam"),
     *                 @SWG\Property(property="passed", type="boolean"),
     *                 @SWG\Property(property="score", type="integer", description="Percentage score multiplied by 100 for whole number (98% = 0.98 * 100 = 98)"),
     *             )
     *         ),
     *     )
     * )
     */
    public function getExamHistory($cid) {

    }
}
