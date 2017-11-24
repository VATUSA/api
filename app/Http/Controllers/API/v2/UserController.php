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
     * @param int $cid
     * @param string $facility
     * @param string $role
     * @return array|string
     *
     * @TODO Add role, add action log entry
     *
     * @SWG\Put(
     *     path="/user/(cid)/roles/(facility)/(role)",
     *     summary="Assign new role",
     *     description="Assign new role",
     *     produces={"application/json"},
     *     tags={"user"},
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
            return response()->json(generate_error("Facility not found or invalid"), 404);
        }

        if (!RoleHelper::canModify(\Auth::user(), $facility, $role)) {
            return response()->json(generate_error("Forbidden"), 403);
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
     *     summary="Delete role",
     *     description="Delete role",
     *     produces={"application/json"},
     *     tags={"user"},
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
     * @SWG\Put(
     *     path="/user/(cid)/transfer",
     *     summary="Submit transfer request",
     *     description="Submit transfer request",
     *     produces={"application/json"},
     *     tags={"user"},
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
     * @SWG\Post(
     *     path="/user/(cid)/rating",
     *     summary="Submit rating change",
     *     description="Submit rating change",
     *     produces={"application/json"},
     *     tags={"user"},
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
}
