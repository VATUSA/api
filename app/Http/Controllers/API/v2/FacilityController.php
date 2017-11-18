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
     * @TODO: Add support for reassigning API Keys, ULS Secrets, etc.
     *
     * @SWG\Post(
     *     path="/facility/{id}",
     *     summary="Update facility information (role restricted)",
     *     description="Update facility information (role restricted)",
     *     produces={"application/json"},
     *     tags={"facility"},
     *     security={"json","session"},
     *     @SWG\Parameter(name="id", in="query", description="Facility IATA ID", required=true, type="string"),
     *     @SWG\Parameter(name="url", in="formData", description="Change facility URL", type="string"),
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
     *         @SWG\Schema(ref="#/definitions/OK"),
     *         examples={"application/json":{"status"="OK"}}
     *     )
     * )
     */
    public function postFacility(Request $request, $id) {
        $facility = Facility::find($id);
        if (!$facility || !$facility->active) {
            return response()->json(generate_error("Facility not found or not active", true), 404);
        }

        if (!RoleHelper::has(\Auth::user()->cid, $id, "ATM") &&
            !RoleHelper::has(\Auth::user()->cid, $id, "DATM") &&
            !RoleHelper::has(\Auth::user()->cid, $id, "WM") &&
            !RoleHelper::isVATUSAStaff(\Auth::user()->cid)) {
            return response()->json(generate_error("Forbidden", true), 403);
        }

        if ($request->has("url") && filter_var($request->input("url"), FILTER_VALIDATE_URL)) {
            $facility->url = $request->input("url");
            $facility->save();
        }

        return response()->json(["status" => "OK"]);
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
}
