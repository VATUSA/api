<?php

namespace App\Http\Controllers\API\v2;

use App\Helpers\AuthHelper;
use App\Helpers\RatingHelper;
use App\Helpers\RoleHelper;
use App\Role;
use App\Transfer;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Facility;

/**
 * Class FacilityController
 * @package App\Http\Controllers\API\v2
 */
class FacilityController extends APIController
{
    /**
     * @return array|string
     *
     * @SWG\Get(
     *     path="/facility",
     *     summary="Get list of VATUSA facilities",
     *     description="Get list of VATUSA facilities",
     *     produces={"application/json"},
     *     tags={"facility"},
     *     @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(
     *             type="array",
     *             @SWG\Items(
     *                 type="object",
     *                 @SWG\Property(property="id", type="string", description="IATA identifier of facility"),
     *                 @SWG\Property(property="name", type="string", description="Name of facility"),
     *                 @SWG\Property(property="url", type="string", description="Facility web address")
     *             ),
     *         ),
     *         examples={
     *              "application/json":{
     *                      {"id":"ZAE","name":"Academy","url":"https://www.vatusa.net"},
     *              }
     *         }
     *     )
     * )
     */
    public function getIndex() {
        if (\Cache::has("facility.list.active")) {
            return \Cache::get("facility.list.active");
        }

        $facilities = \FacilityHelper::getFacilities("name", $all);
        $data = [];
        foreach ($facilities as $facility) {
            $data[] = [
                'id' => $facility->id,
                'name' => $facility->name,
                'url' => $facility->url
            ];
        }
        $data = json_encode($data);

        // Store for 24 hours
        \Cache::put("facility.list.active", $data, 24 * 60);

        return $data;
    }

    /**
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     *
     * @SWG\Get(
     *     path="/facility/{id}",
     *     summary="Get facility information",
     *     description="Get facility information",
     *     produces={"application/json"},
     *     tags={"facility"},
     *     @SWG\Parameter(name="id", in="query", description="Facility IATA ID", required=true, type="string"),
     *     @SWG\Response(
     *         response="404",
     *         description="Not found or not active",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Facility not found or not active"}},
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(
     *             type="object",
     *             @SWG\Property(property="id", type="string", description="IATA identifier of facility"),
     *             @SWG\Property(property="name", type="string", description="Name of facility"),
     *             @SWG\Property(property="url", type="string", description="Facility web address"),
     *             @SWG\Property(
     *                 property="roles",
     *                 type="array",
     *                 @SWG\Items(
     *                     type="object",
     *                     @SWG\Property(property="cid", type="integer", description="CERT ID"),
     *                     @SWG\Property(property="name", type="string", description="User's name"),
     *                     @SWG\Property(property="role", type="string", description="Role")
     *                 ),
     *             ),
     *             @SWG\Property(
     *                 property="stats",
     *                 type="object",
     *                 @SWG\Property(property="controllers", type="integer", description="Number of controllers on facility roster"),
     *                 @SWG\Property(property="pendingTransfers", type="integer", description="Number of pending transfers to facility"),
     *             ),
     *         ),
     *         examples={
     *              "application/json":{
     *                      "id":"ZAE","name":"Academy","url":"https://www.vatusa.net",
     *                      "roles":{{"cid":876594,"name":"Daniel Hawton","role":"MTR"}},
     *                      "stats":{"controllers":123,"pendingTransfers":0}
     *              }
     *         }
     *     )
     * )
     */
    public function getFacility($id) {
        $facility = Facility::find($id);
        if (!$facility || !$facility->active) {
            return response()->json(generate_error("Facility not found or not active", true), 404);
        }

        if (\Cache::has("facility.$id.info")) {
            return \Cache::get("facility.$id.info");
        }

        $data = [
            'id' => $facility->id,
            'name' => $facility->name,
            'url' => $facility->url
        ];
        foreach (Role::where('facility', $id)->get() as $role) {
            $data['role'][] = [
                'cid' => $role->cid,
                'name' => $role->user->fullname(),
                'role' => $role->role
            ];
        }
        $data['stats']['controllers'] = User::where('facility', $id)->count();
        $data['stats']['pendingTransfers'] = Transfer::where('to', $id)->where('status', Transfer::$pending)->count();

        $json = encode_json($data);

        \Cache::put("facility.$id.info", $json, 60);

        return $json;
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     *
     * @SWG\Post(
     *     path="/facility/{id}",
     *     summary="Update facility information. Requires JWT or Session Cookie",
     *     description="Update facility information. Requires JWT or Session Cookie",
     *     produces={"application/json"},
     *     tags={"facility"},
     *     security={"json","session"},
     *     @SWG\Parameter(name="id", in="query", description="Facility IATA ID", required=true, type="string"),
     *     @SWG\Parameter(name="url", in="formData", description="Change facility URL, role restricted [ATM, DATM, WM]", type="string"),
     *     @SWG\Parameter(name="apikey", in="formData", type="string", description="Request new API Key for facility, role restricted [ATM, DATM, WM]"),
     *     @SWG\Parameter(name="apikeySandbox", in="formData", type="string", description="Request new Sandbox API Key for facility, role restricted [ATM, DATM, WM]"),
     *     @SWG\Parameter(name="ulsSecret", in="formData", type="string", description="Request new ULS Secret, role restricted [ATM, DATM, WM]"),
     *     @SWG\Parameter(name="ulsReturn", in="formData", type="string", description="Set new ULS return point, role restricted [ATM, DATM, WM]"),
     *     @SWG\Parameter(name="ulsDevReturn", in="formData", type="string", description="Set new ULS developmental return point, role restricted [ATM, DATM, WM]"),
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
     *         description="Not found or not active",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Facility not found or not active"}},
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(
     *             type="object",
     *             @SWG\Property(property="status",type="string"),
     *             @SWG\Property(property="apikey",type="string"),
     *             @SWG\Property(property="apikeySandbox",type="string"),
     *             @SWG\Property(property="ulsSecret", type="string"),
     *         ),
     *         examples={"application/json":{"status"="OK"}}
     *     )
     * )
     */
    public function postFacility(Request $request, $id) {
        $facility = Facility::find($id);
        if (!$facility || !$facility->active) {
            return response()->json(generate_error("Facility not found or not active", true), 404);
        }

        if (!RoleHelper::has(\Auth::user()->cid, $id, ["ATM","DATM","WM"]) &&
            !RoleHelper::isVATUSAStaff(\Auth::user()->cid)) {
            return response()->json(generate_error("Forbidden", true), 403);
        }

        $data = [];

        if ($request->has("url") && filter_var($request->input("url"), FILTER_VALIDATE_URL)) {
            $facility->url = $request->input("url");
            $facility->save();
        }

        if ($request->has('apikey')) {
            if (\Auth::check() && RoleHelper::has(\Auth::user()->cid, $facility->id, ['ATM','DATM','WM'])) {
                $data['apikey'] = randomPassword(16);
                $facility->apikey = $data['apikey'];
                $facility->save();
            } else {
                return response()->json(generate_error("Forbidden"), 403);
            }
        }

        if ($request->has('apikeySandbox')) {
            if (\Auth::check() && RoleHelper::has(\Auth::user()->cid, $facility->id, ['ATM','DATM','WM'])) {
                $data['apikeySandbox'] = randomPassword(16);
                $facility->api_sandbox_key = $data['apikeySandbox'];
                $facility->save();
            } else {
                return response()->json(generate_error("Forbidden"), 403);
            }
        }

        if ($request->has('ulsSecret')) {
            if (\Auth::check() && RoleHelper::has(\Auth::user()->cid, $facility->id, ['ATM','DATM','WM'])) {
                $data['ulsSecret'] = substr(hash('sha512', microtime()), -16);
                $facility->uls_secret = $data['ulsSecret'];
                $facility->save();
            } else {
                return response()->json(generate_error("Forbidden"), 403);
            }
        }

        if ($request->has('ulsReturn')) {
            if (\Auth::check() && RoleHelper::has(\Auth::user()->cid, $facility->id, ['ATM','DATM','WM'])) {
                $facility->uls_return = $request->input("ulsReturn");
                $facility->save();
            } else {
                return response()->json(generate_error("Forbidden"), 403);
            }
        }

        if ($request->has('ulsDevReturn')) {
            if (\Auth::check() && RoleHelper::has(\Auth::user()->cid, $facility->id, ['ATM','DATM','WM'])) {
                $facility->uls_devreturn = $request->input("ulsDevReturn");
                $facility->save();
            } else {
                return response()->json(generate_error("Forbidden"), 403);
            }
        }

        return response()->json(array_merge(['status' => 'OK'], $data));
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     *
     * @SWG\Get(
     *     path="/facility/{id}/staff",
     *     summary="Get facility staff list",
     *     description="Get facility staff list",
     *     produces={"application/json"},
     *     tags={"facility"},
     *     @SWG\Parameter(name="id", in="query", description="Facility IATA ID", required=true, type="string"),
     *     @SWG\Response(
     *         response="404",
     *         description="Not found or not active",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Facility not found or not active"}},
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(
     *             type="object",
     *             @SWG\Property(
     *                 property="ATM",
     *                 type="array",
     *                 @SWG\Items(
     *                     type="object",
     *                     @SWG\Property(property="cid", type="integer"),
     *                     @SWG\Property(property="name", type="string"),
     *                 )
     *             ),
     *             @SWG\Property(
     *                 property="DATM",
     *                 type="array",
     *                 @SWG\Items(
     *                     type="object",
     *                     @SWG\Property(property="cid", type="integer"),
     *                     @SWG\Property(property="name", type="string"),
     *                 )
     *             ),
     *             @SWG\Property(
     *                 property="TA",
     *                 type="array",
     *                 @SWG\Items(
     *                     type="object",
     *                     @SWG\Property(property="cid", type="integer"),
     *                     @SWG\Property(property="name", type="string"),
     *                 )
     *             ),
     *             @SWG\Property(
     *                 property="EC",
     *                 type="array",
     *                 @SWG\Items(
     *                     type="object",
     *                     @SWG\Property(property="cid", type="integer"),
     *                     @SWG\Property(property="name", type="string"),
     *                 )
     *             ),
     *             @SWG\Property(
     *                 property="FE",
     *                 type="array",
     *                 @SWG\Items(
     *                     type="object",
     *                     @SWG\Property(property="cid", type="integer"),
     *                     @SWG\Property(property="name", type="string"),
     *                 )
     *             ),
     *             @SWG\Property(
     *                 property="WM",
     *                 type="array",
     *                 @SWG\Items(
     *                     type="object",
     *                     @SWG\Property(property="cid", type="integer"),
     *                     @SWG\Property(property="name", type="string"),
     *                 )
     *             ),
     *         ),
     *     )
     * )
     */
    public function getStaff(Request $request, $id) {
        $facility = Facility::find($id);
        if (!$facility || !$facility->active) {
            return response()->json(generate_error("Facility not found or not active", true), 404);
        }

        if (\Cache::has("facility.$id.staff")) {
            return \Cache::get("facility.$id.staff");
        }

        $data = [];
        $positions = ["ATM","DATM","TA","EC","FE","WM"];
        foreach ($positions as $position) {
            foreach (Role::where("facility", $facility->id)->where("role", $position)->get() as $row) {
                $data[$position][] = [
                    "cid" => $row->cid,
                    "name" => $row->user->fullname(),
                ];
            }
        }

        $json = encode_json($data);

        \Cache::put("facility.$id.staff", $json, 24*60);
        return $json;
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     *
     * @SWG\Get(
     *     path="/facility/{id}/roster",
     *     summary="Get facility roster",
     *     description="Get facility staff.  If api key specified, email properties are defined",
     *     produces={"application/json"},
     *     tags={"facility"},
     *     @SWG\Parameter(name="id", in="query", description="Facility IATA ID", required=true, type="string"),
     *     @SWG\Response(
     *         response="404",
     *         description="Not found or not active",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Facility not found or not active"}},
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(
     *             type="array",
     *             @SWG\Items(
     *                 type="object",
     *                 @SWG\Property(property="cid", type="integer"),
     *                 @SWG\Property(property="lastname", type="string"),
     *                 @SWG\Property(property="firstname", type="string"),
     *                 @SWG\Property(property="email", type="string", description="Empty if no API Key defined"),
     *                 @SWG\Property(property="rating", type="string", description="Short rating string (S1, S2, etc)"),
     *                 @SWG\Property(property="intRating", type="integer", description="Standard rating integer (OBS=1, S1=2, etc)"),
     *                 @SWG\Property(property="joinDate", type="string", description="Date joined facility (YYYY-MM-DD)"),
     *                 @SWG\Property(property="promotionEligible", type="boolean"),
     *                 @SWG\Property(property="roles", type="array", @SWG\Items(title="role", type="string"))
     *             )
     *         ),
     *     )
     * )
     */
    public function getRoster(Request $request, $id) {
        $facility = Facility::find($id);
        if (!$facility || !$facility->active) {
            return response()->json(generate_error("Facility not found or not active", true), 404);
        }
        $apikey = false;
        if ($request->has("apikey")) {
            $apikey = AuthHelper::validApiKey($_SERVER['REMOTE_ADDR'], $request->input("apikey"), $facility->id);
        }

        $data = [];
        foreach(User::where("facility", $facility->id)->orderBy("lname")->orderBy("fname")->get() as $user) {
            $tmp = [
                "cid" => $user->cid,
                "lastname" => $user->lname,
                "firstname" => $user->fname,
                "email" => ($apikey) ? $user->email : '',
                "rating" => RatingHelper::intToShort($user->rating),
                "intRating" => $user->rating,
                "joinDate" => Carbon::createFromFormat("Y-m-d H:i:s", $user->facility_join)->format("Y-m-d"),
                "promotionEligible" => (bool)$user->promotionEligible(),
                "roles" => []
            ];
            foreach($user->roles()->where("facility", $facility->id)->get() as $role) {
                $tmp["roles"][] = $role->role;
            }
            $data[] = $tmp;
        }
        $json = encode_json($data);

        return $json;
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param string $id
     * @param integer $cid
     * @return \Illuminate\Http\JsonResponse
     *
     * @SWG\Delete(
     *     path="/facility/{id}/roster/{cid}",
     *     summary="Delete member from facility roster. JWT or Session Cookie required",
     *     description="Delete member from facility roster.  JWT or Session Cookie required",
     *     produces={"application/json"},
     *     tags={"facility"},
     *     security={"jwt","session"},
     *     @SWG\Parameter(name="id", in="query", description="Facility IATA ID", required=true, type="string"),
     *     @SWG\Parameter(name="cid", in="query", description="CID of controller", required=true, type="integer"),
     *     @SWG\Parameter(name="reason", in="formData", description="Reason for deletion", required=true, type="string"),
     *     @SWG\Response(
     *         response="400",
     *         description="Malformed request, missing required parameter",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Malformed request"}},
     *     ),
     *     @SWG\Response(
     *         response="401",
     *         description="Unauthenticated",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Unauthenticated"}},
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Forbidden -- needs to have role of ATM, DATM or VATUSA Division staff member",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","message"="Forbidden"}},
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Not found or not active",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Facility not found or not active"}},
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(ref="#/definitions/OK"),
     *         examples={"application/json":{"status"="OK"}}
     *     )
     * )
     */
    public function deleteRoster(Request $request, string $id, int $cid) {
        $facility = Facility::find($id);
        if (!$facility || !$facility->active) {
            return response()->json(generate_error("Facility not found or not active"), 404);
        }

        if (!RoleHelper::isSeniorStaff(\Auth::user()->cid, $id, false)) {
            return response()->json(generate_error("Forbidden"), 403);
        }

        $user = User::where('cid', $cid)->first();
        if (!$user || $user->facility != $facility->id) {
            return response()->json(generate_error("User not found or not in facility"), 404);
        }

        if (!$request->has("reason") || !$request->filled("reason")) {
            return response()->json(generate_error("Malformed request"), 400);
        }

        $user->removeFromFacility(\Auth::user()->cid, $request->input("reason"));

        return response()->json(["status"=>"OK"]);
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     *
     * @SWG\Get(
     *     path="/facility/{id}/transfers",
     *     summary="Get pending transfers. Requires JWT, API Key or Session Cookie",
     *     description="Get pending transfers. Requires JWT, API Key or Session Cookie",
     *     produces={"application/json"},
     *     tags={"facility"},
     *     security={"jwt","session","apikey"},
     *     @SWG\Parameter(name="id", in="query", description="Facility IATA ID", required=true, type="string"),
     *     @SWG\Response(
     *         response="400",
     *         description="Malformed request, missing required parameter",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Malformed request"}},
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Forbidden -- needs to be a staff member, other than mentor",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","message"="Forbidden"}},
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Not found or not active",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Facility not found or not active"}},
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(
     *             type="array",
     *             @SWG\Items(
     *                 type="object",
     *                 @SWG\Property(property="id", type="integer", description="Transfer ID"),
     *                 @SWG\Property(property="cid", type="integer"),
     *                 @SWG\Property(property="name", type="string"),
     *                 @SWG\Property(property="rating", type="string", description="Short string rating (S1, S2)"),
     *                 @SWG\Property(property="intRating", type="integer", description="Numeric rating (OBS = 1, etc)"),
     *                 @SWG\Property(property="date", type="string", description="Date transfer submitted (YYYY-MM-DD)"),
     *             ),
     *         ),
     *         examples={"application/json":{{"id":991,"cid":876594,"name":"Daniel Hawton","rating":"C1","intRating":5,"date":"2017-11-18"}}}
     *     )
     * )
     */
    public function getTransfers(Request $request, string $id) {
        $facility = Facility::find($id);
        if (!$facility || !$facility->active) {
            return response()->json(generate_error("Facility not found or not active"), 404);
        }

        if (!RoleHelper::isFacilityStaff(\Auth::user()->cid, $id) && !RoleHelper::isVATUSAStaff(\Auth::user()->cid)) {
            return response()->json(generate_error("Forbidden"), 403);
        }

        $transfers = Transfer::where("to", $facility->id)->where("status", Transfer::$pending)->get();
        $data = [];
        foreach($transfers as $transfer) {
            $data[] = [
                'id' => $transfer->id,
                'cid' => $transfer->cid,
                'name' => $transfer->user->fullname(),
                'rating' => RatingHelper::intToShort($transfer->user->rating),
                'intRating' => $transfer->user->rating,
                'date' => $transfer->created_at->format('Y-m-d')
            ];
        }

        return encode_json($data);
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param string $id
     * @param int $transferId
     * @return \Illuminate\Http\JsonResponse
     *
     * @SWG\Post(
     *     path="/facility/{id}/transfers/{transferId}",
     *     summary="Modify transfer request.  JWT or Session cookie required.",
     *     description="Modify transfer request.  JWT or Session cookie required.",
     *     produces={"application/json"},
     *     tags={"facility"},
     *     security={"jwt","session"},
     *     @SWG\Parameter(name="id", in="query", description="Facility IATA ID", required=true, type="string"),
     *     @SWG\Parameter(name="transferId", in="query", description="Transfer ID", type="integer", required=true),
     *     @SWG\Parameter(name="action", in="formData", type="string", enum={"approve","reject"}, description="Action to take on transfer request. Valid values: approve, reject"),
     *     @SWG\Parameter(name="reason", in="formData", type="string", description="Reason for transfer request rejection [required for rejections]"),
     *     @SWG\Response(
     *         response="400",
     *         description="Malformed request, missing required parameter",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Malformed request"}},
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Forbidden -- needs to be a staff member, other than mentor",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","message"="Forbidden"}},
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Not found or not active",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Facility not found or not active"}},
     *     ),
     *     @SWG\Response(
     *         response="410",
     *         description="Gone",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Transfer is not pending"}},
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(ref="#/definitions/OK"),
     *         examples={"application/json":{"status"="OK"}}
     *     )
     * )
     */
    public function postTransfer(Request $request, string $id, int $transferId) {
        $facility = Facility::find($id);
        if (!$facility || !$facility->active) {
            return response()->json(generate_error("Facility not found or not active"), 404);
        }

        if (!RoleHelper::isSeniorStaff(\Auth::user()->cid, $facility->id, false) && !RoleHelper::isVATUSAStaff(\Auth::user()->cid)) {
            return response()->json(generate_error("Forbidden"), 403);
        }

        $transfer = Transfer::find($transferId);
        if (!$transfer) {
            return response()->json(generate_error("Transfer request not found"), 404);
        }

        if ($transfer->status !== Transfer::$pending) {
            return response()->json(generate_error("Transfer is not pending"), 410);
        }

        if ($transfer->to !== $facility->id) {
            return response()->json(generate_error("Forbidden"), 403);
        }

        if(!in_array($request->input("action"), ["accept","reject"]) ||
            ($request->input("action") === "reject" && !$request->filled("reason"))) {

            return response()->json(generate_error("Malformed request"), 400);
        }

        if ($request->input("action") === "accept") {
            $transfer->accept(\Auth::user()->cid);
        } else {
            $transfer->reject(\Auth::user()->cid, $request->input("reason"));
        }

        return response()->json(['status'=>"OK"]);
    }
}
