<?php

namespace App\Http\Controllers\API\v2;

use App\Action;
use App\Helpers\AuthHelper;
use App\Helpers\RoleHelper;
use App\User;
use Exception;
use Illuminate\Http\Request;
use App\SoloCert;
use Illuminate\Support\Facades\Auth;

/**
 * Class SoloController
 * @package App\Http\Controllers\API\v2
 */
class SoloController extends APIController
{
    /**
     * @SWG\Get(
     *     path="/solo",
     *     summary="Get list of active solo certifications.",
     *     description="Get list of active solo certifications.",
     *     produces={"application/json"},
     *     tags={"solo"},
     *     @SWG\Parameter(name="position", in="query", type="string", description="Filter for position"),
     *     @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(
     *             type="array",
     *             @SWG\Items(
     *                 type="object",
     *                 @SWG\Property(property="id", type="integer", description="Solo Certification id"),
     *                 @SWG\Property(property="cid",type="integer",description="CERT ID of user"),
     *                 @SWG\Property(property="lastname",type="string",description="Last name"),
     *                 @SWG\Property(property="firstname",type="string",description="First name"),
     *                 @SWG\Property(property="position", type="string", description="Position ID (XYZ_APP, ZZZ_CTR)"),
     *                 @SWG\Property(property="expDate", type="string", description="Expiration Date (YYYY-MM-DD)"),
     *             ),
     *         ),
     *     )
     * ),
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getIndex(Request $request)
    {
        $solos = SoloCert::where('expires', '>', \DB::raw("NOW()"));
        if ($request->has("position")) {
            $solos = $solos->where("position", $request->input("position"));
        }

        return response()->api($solos->get()->toArray());
    }

    /**
     * @SWG\Post(
     *     path="/solo",
     *     summary="Submit new solo certification. [Key]",
     *     description="Submit new solo certification. Requires API Key, JWT, or Session Cookie (required roles:
    [N/A for API Key] ATM, DATM, TA, INS)", produces={"application/json"}, tags={"solo"},
     *     security={"apikey","jwt","session"},
     * @SWG\Parameter(name="cid", in="formData", type="integer", required=true, description="CERT ID"),
     * @SWG\Parameter(name="position", in="formData", type="string", required=true, description="Position ID
    (XYZ_APP, ZZZ_CTR)"),
     * @SWG\Parameter(name="expDate", in="formData", type="string", required=true, description="Date of expiration
    (YYYY-MM-DD)"),
     * @SWG\Response(
     *         response="400",
     *         description="Malformed request, check format of position, expDate",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={{"application/json":{"status"="error","message"="Invalid
     *         position"}},{"application/json":{"status"="error","message"="Invalid expDate"}}},
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
     *         @SWG\Schema(
     *             type="object",
     *             @SWG\Property(property="status", type="string"),
     *             @SWG\Property(property="id", type="integer", description="ID number of solo certification"),
     *         ),
     *         examples={"application/json":{"status"="OK","id"=1234}}
     *     )
     * ),
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array|\Illuminate\Http\JsonResponse|string
     */
    public function postSolo(Request $request)
    {
        $apikey = AuthHelper::validApiKeyv2($request->input('apikey', null));
        if (!$apikey && !\Auth::check()) {
            return response()->api(generate_error("Unauthorized"), 401);
        }

        if (\Auth::check() && !(RoleHelper::isFacilityStaff() ||
                RoleHelper::isVATUSAStaff() ||
                RoleHelper::isInstructor())) {
            return response()->api(generate_error("Forbidden"), 403);
        }

        if (!$request->has("cid") || !$request->has("position") || !$request->has("expDate")) {
            return response()->api(generate_error("Malformed request."), 400);
        }

        $cid = $request->input("cid");
        if (!User::where("cid", $cid)->count()) {
            return response()->api(generate_error("Invalid controller."), 400);
        }

        $position = strtoupper($request->input("position"));
        if (!preg_match("/^([A-Z0-9]{2,3})_(APP|CTR)$/", $position)) {
            return response()->api(generate_error("Malformed position."), 400);
        }

        $exp = $request->input("expDate", null);
        try {
            $cExp = \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $exp, "UTC");
        } catch (InvalidArgumentException $e) {
            return response()->api(generate_error("Malformed request, invalid expire date format (Y-m-d)."),
                400);
        }

        if ($cExp->diffInDays() > 30) {
            return response()->api(generate_error("Invalid expiration date, must be in at most 30 days."), 400);
        }

        if ($cExp->isPast()) {
            return response()->api(generate_error("Invalid expiration date, cannot be in the past."), 400);
        }

        if (!isTest()) {
            SoloCert::updateOrCreate(
                ['cid' => $cid, 'position' => $position],
                ['expires' => $exp]
            );

            $log = new Action();
            $log->to = $cid;
            $log->log = "Solo Cert issued for " . $position . " by " . ((Auth::user()) ? Auth::user()->fullname() : "API") . ". Expires: " . $exp;
            $log->save();
        }

        return response()->ok();
    }

    /**
     * @SWG\Delete(
     *     path="/solo",
     *     summary="Delete solo certification. [Key]",
     *     description="Delete solo certification. Pass the DB ID OR both CID and Position. Requires API Key, JWT, or
     *     Session cookie (required roles: [N/A
    for API Key] ATM, DATM, TA, INS).",
     *     produces={"application/json"}, tags={"solo"},
     *     security={"apikey","jwt","session"},
     * @SWG\Parameter(name="id", in="formData", type="integer", required=false, description="Endorsement ID. Use this
     *                           OR both CID and Position."),
     * @SWG\Parameter(name="cid", in="formData", type="integer", required=true, description="Vatsim ID"),
     * @SWG\Parameter(name="position", in="formData", type="string", required=true, description="Position ID (XYZ_APP,
    ZZZ_CTR)"),
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
     * ),
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function deleteSolo(Request $request)
    {
        $apikey = AuthHelper::validApiKeyv2($request->input('apikey', null));
        if (!$apikey && !\Auth::check()) {
            return response()->api(generate_error("Unauthorized"), 401);
        }

        if (\Auth::check() && (!RoleHelper::isFacilityStaff() &&
                !RoleHelper::isVATUSAStaff() &&
                !RoleHelper::isInstructor())) {
            return response()->api(generate_error("Forbidden"), 403);
        }

        if (!isTest()) {
            if ($request->input("id", null)) {
                try {
                    $cert = SoloCert::findOrFail($request->input("id", null));
                    $log = new Action();
                    $log->to = $cert->cid;
                    $log->log = "Solo Cert revoked for " . $cert->position . " by " . ((Auth::user()) ? Auth::user()->fullname() : "API");
                    $log->save();
                    $cert->delete();
                } catch (Exception $e) {
                    return response()->api(generate_error("Certification not found"), 404);
                }
            } else {
                $cert = SoloCert::where('cid', $request->input("cid", null))
                    ->where("position", strtoupper($request->input("position", null)))->first();
                if (!$cert) {
                    return response()->api(generate_error("Certification not found"), 404);
                } else {
                    $log = new Action();
                    $log->to = $cert->cid;
                    $log->log = "Solo Cert revoked for " . $cert->position . " by " . ((Auth::user()) ? Auth::user()->fullname() : "API");
                    $log->save();
                    $cert->delete();
                }
            }
        }

        return response()->ok();
    }
}
