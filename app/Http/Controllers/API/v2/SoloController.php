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
use App\SoloCert;

/**
 * Class SoloController
 * @package App\Http\Controllers\API\v2
 */
class SoloController extends APIController
{
    /**
     * @SWG\Get(
     *     path="/solo",
     *     summary="(DONE) Get list of active solo certifications",
     *     description="(DONE) Get list of active solo certifications",
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
     */
    public function getIndex(Request $request) {
        $solos = SoloCert::where('expires', '>', \DB::raw("NOW()"));
        if ($request->has("position")) {
            $solos = $solos->where("position", $request->input("position"));
        }
        return response()->api($solos->get()->toArray());
    }

    /**
     * @SWG\Post(
     *     path="/solo",
     *     summary="(DONE) Put new solo certification. Requires JWT, API Key, or Session cookie",
     *     description="(DONE) Put new solo certification. Requires JWT, API Key, or Session cookie (required roles: [N/A for API Key] ATM, DATM, TA, INS)",
     *     produces={"application/json"},
     *     tags={"solo"},
     *     security={"apikey","jwt","session"},
     *     @SWG\Parameter(name="cid", in="formData", type="integer", required=true, description="CERT ID"),
     *     @SWG\Parameter(name="position", in="formData", type="string", required=true, description="Position ID (XYZ_APP, ZZZ_CTR)"),
     *     @SWG\Parameter(name="expDate", in="formData", type="string", required=true, description="Date of expiration (YYYY-MM-DD)"),
     *     @SWG\Response(
     *         response="400",
     *         description="Malformed request, check format of position, expDate",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={{"application/json":{"status"="error","message"="Invalid position"}},{"application/json":{"status"="error","message"="Invalid expDate"}}},
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
     *         @SWG\Schema(
     *             type="object",
     *             @SWG\Property(property="status", type="string"),
     *             @SWG\Property(property="id", type="integer", description="ID number of solo certification"),
     *         ),
     *         examples={"application/json":{"status"="OK","id"=1234}}
     *     )
     * ),
     */
    public function postSolo(Request $request) {
        if (!$request->has("apikey")) {
            if (!\Auth::check()) {
                return response()->api(generate_error("Unauthorized"), 401);
            }
            if (!RoleHelper::isFacilityStaff() &&
                !RoleHelper::isVATUSAStaff() &&
                !RoleHelper::isInstructor()) {

                return response()->api(generate_error("Forbidden"), 403);
            }
        }

        if (!$request->has("cid") || !$request->has("position") || !$request->has("expDate")) {
            return response()->api(generate_error("Malformed request"), 400);
        }

        $position = $request->input("position");
        if (!preg_match("/^([A-Z0-9]{2,3})_(APP|CTR)$/", $request->input("position"))) {
            return response()->api(generate_error("Malformed position"), 400);
        }

        $exp = $request->input("expDate", null);
        if (!$exp || !preg_match("/^\d{4}-\d{2}-\d{2}/", $exp)) {
            return generate_error("Malformed or missing field", false);
        }

        if (\Carbon\Carbon::createFromFormat('Y-m-d', $exp)->diffInDays() > 30) {
            return response()->json(generate_error("Invalid date"), 400);
        }

        if (!isTest()) {
            $solo = new SoloCert();
            $solo->cid = $cid;
            $solo->position = $position;
            $solo->expires = $exp;
            $solo->save();
        }

        return response()->api(['status' => 'OK']);
    }

    /**
     * @SWG\Delete(
     *     path="/solo",
     *     summary="(DONE) Delete solo certification. Requires JWT, API Key, or Session cookie",
     *     description="(DONE) Delete solo certification. Requires JWT, API Key, or Session cookie (required roles: [N/A for API Key] ATM, DATM, TA, INS)",
     *     produces={"application/json"},
     *     tags={"solo"},
     *     security={"apikey","jwt","session"},
     *     @SWG\Parameter(name="cid", in="formData", type="integer", required=true, description="CERT ID"),
     *     @SWG\Parameter(name="position", in="formData", type="string", required=true, description="Position ID (XYZ_APP, ZZZ_CTR)"),
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
     * ),
     */
    public function deleteSolo() {
        if (!$request->has("apikey")) {
            if (!\Auth::check()) {
                return response()->api(generate_error("Unauthorized"), 401);
            }
            if (!RoleHelper::isFacilityStaff() &&
                !RoleHelper::isVATUSAStaff() &&
                !RoleHelper::isInstructor()) {

                return response()->api(generate_error("Forbidden"), 403);
            }
        }

        SoloCert::where('cid', $request->input("cid"))
            ->where("position", $request->input("position"))
            ->delete();

        return response()->api(["status" => "OK"]);
    }
}
