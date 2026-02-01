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
     * @OA\Get(
     *     path="/solo",
     *     summary="Get list of active solo certifications.",
     *     description="Get list of active solo certifications.",
     *     tags={"solo"},
     *     @OA\Parameter(name="position", in="query", @OA\Schema(type="string"), description="Filter for position"),
     *     @OA\Response(
     *         response="200",
     *         description="OK",
     *         @OA\Schema(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", @OA\Schema(type="integer"), description="Solo Certification id"),
     *                 @OA\Property(property="cid",type="integer",description="CERT ID of user"),
     *                 @OA\Property(property="lastname",type="string",description="Last name"),
     *                 @OA\Property(property="firstname",type="string",description="First name"),
     *                 @OA\Property(property="position", @OA\Schema(type="string"), description="Position ID (XYZ_APP, ZZZ_CTR)"),
     *                 @OA\Property(property="expDate", @OA\Schema(type="string"), description="Expiration Date (YYYY-MM-DD)"),
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
     * @OA\Post(
     *     path="/solo",
     *     summary="Submit new solo certification. [Key]",
     *     description="Submit new solo certification. Requires API Key, JWT, or Session Cookie (required roles:
    [N/A for API Key] ATM, DATM, TA, INS, MTR)",  tags={"solo"},
     *     security={"apikey","jwt","session"},
     * @OA\RequestBody(
     * @OA\MediaType(
     * mediaType="application/x-www-form-urlencoded",
     * @OA\Schema(
     * @OA\Parameter(name="cid", @OA\Schema(type="integer"), required=true, description="CERT ID"),
     * @OA\Parameter(name="position", @OA\Schema(type="string"), required=true, description="Position ID
    (XYZ_APP, ZZZ_CTR)"),
     * @OA\Parameter(name="expDate", @OA\Schema(type="string"), required=true, description="Date of expiration
    (YYYY-MM-DD)"),
     * )
     * )
     * ),
     * @OA\Response(
     *         response="400",
     *         description="Malformed request, check format of position, expDate",
     *         @OA\Schema(ref="#/components/schemas/error"),
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
     *         @OA\Schema(
     *             type="object",
     *             @OA\Property(property="status", @OA\Schema(type="string")),
     *             @OA\Property(property="id", @OA\Schema(type="integer"), description="ID number of solo certification"),
     *         ),
     *         
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

        if (!$request->has("cid") || !$request->has("position") || !$request->has("expDate")) {
            return response()->api(generate_error("Malformed request."), 400);
        }

        $cid = $request->input("cid");
        $user = User::find($cid);
        if (!$user) {
            return response()->api(generate_error("Invalid controller."), 400);
        }

        if (\Auth::check() && !(RoleHelper::isSeniorStaff(Auth::user(),$user->facility,true) ||
                RoleHelper::isVATUSAStaff(Auth::user()) ||
                RoleHelper::isTrainingStaff(Auth::user(),true,$user->facility))) {
            return response()->api(generate_error("Forbidden"), 403);
        }

        $position = strtoupper($request->input("position"));
        if (!preg_match("/^([A-Z0-9]{2,3})_(TWR|APP|DEP|CTR)$/", $position)) {
            return response()->api(generate_error("Malformed position."), 400);
        }

        $exp = $request->input("expDate", null);
        try {
            $cExp = \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $exp, "UTC");
        } catch (InvalidArgumentException $e) {
            return response()->api(generate_error("Malformed request, invalid expire date format (Y-m-d)."),
                400);
        }

        if ($cExp->diffInDays() > 45) {
            return response()->api(generate_error("Invalid expiration date, must be in at most 45 days."), 400);
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
     * @OA\Delete(
     *     path="/solo",
     *     summary="Delete solo certification. [Key]",
     *     description="Delete solo certification. Pass the DB ID OR both CID and Position. Requires API Key, JWT, or
     *     Session cookie (required roles: [N/A
    for API Key] ATM, DATM, TA, INS, MTR).",
     *      tags={"solo"},
     *     security={"apikey","jwt","session"},
     * @OA\RequestBody(
     * @OA\MediaType(
     * mediaType="application/x-www-form-urlencoded",
     * @OA\Schema(
     * @OA\Parameter(name="id", @OA\Schema(type="integer"), required=false, description="Endorsement ID.
     *      Use this
     *                           OR both CID and Position."),
     * @OA\Parameter(name="cid", @OA\Schema(type="integer"), required=true, description="Vatsim ID"),
     * @OA\Parameter(name="position", @OA\Schema(type="string"), required=true, description="Position ID (XYZ_APP,
    ZZZ_CTR)"),
     * )
     * )
     * ),
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

        if (!isTest()) {
            if ($request->input("id", null)) {
                try {
                    $user = User::find($cert->cid);
                    if (\Auth::check() && !(RoleHelper::isSeniorStaff(Auth::user(),$user->facility,true) ||
                            RoleHelper::isVATUSAStaff(Auth::user()) ||
                            RoleHelper::isTrainingStaff(Auth::user(),true,$user->facility))) {
                        return response()->api(generate_error("Forbidden"), 403);
                    }

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
                    $user = User::find($cert->cid);
                    if (\Auth::check() && !(RoleHelper::isSeniorStaff(Auth::user(),$user->facility,true) ||
                            RoleHelper::isVATUSAStaff(Auth::user()) ||
                            RoleHelper::isTrainingStaff(Auth::user(),true,$user->facility))) {
                        return response()->api(generate_error("Forbidden"), 403);
                    }

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
