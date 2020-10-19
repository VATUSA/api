<?php

namespace App\Http\Controllers\API\v2;

use App\Helpers\AuthHelper;
use App\Helpers\FacilityHelper;
use App\Helpers\RatingHelper;
use App\Helpers\RoleHelper;
use App\ReturnPaths;
use App\Role;
use App\TMUFacility;
use App\TMUNotice;
use App\Transfer;
use App\User;
use App\Promotion;
use App\Visit;
use Illuminate\Http\Request;
use App\Facility;
use Jose\Component\KeyManagement\JWKFactory;

/**
 * Class FacilityController
 *
 * @package App\Http\Controllers\API\v2
 */
class FacilityController extends APIController
{
    /**
     * @return array|string
     *
     * @SWG\Get(
     *     path="/facility",
     *     summary="Get list of VATUSA facilities.",
     *     description="Get list of VATUSA facilities.",
     *     produces={"application/json"},
     *     tags={"facility"},
     *     @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(
     *             type="array",
     *             @SWG\Items(
     *                 ref="#/definitions/Facility"
     *             ),
     *         ),
     *         examples={
     *              "application/json":{
     *                      {"id": "HCF","name": "Honolulu CF","url": "http://www.hcfartcc.net","region": 7},
     *                      {"id":"ZAB","name":"Albuquerque ARTCC","url":"http:\/\/www.zabartcc.org","region":8},
     *              }
     *         }
     *     )
     * )
     */
    public function getIndex()
    {
        $data = Facility::where("active", 1)->get()->toArray();

        return response()->ok($data);
    }

    /**
     * @param $id
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @SWG\Get(
     *     path="/facility/{id}",
     *     summary="Get facility information.",
     *     description="Get facility information.",
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
     *             @SWG\Property(property="facility", ref="#/definitions/Facility"),
     *             @SWG\Property(
     *                 property="roles",
     *                 type="array",
     *                 @SWG\Items(
     *                     ref="#/definitions/Role",
     *                 ),
     *             ),
     *             @SWG\Property(
     *                 property="stats",
     *                 type="object",
     *                 @SWG\Property(property="controllers", type="integer", description="Number of controllers on
    facility roster"),
     *                 @SWG\Property(property="pendingTransfers", type="integer", description="Number of pending
    transfers to facility"),
     *             ),
     *         ),
     *         examples={
     *              "application/json":{
     *                      {"id":"HCF","name":"Honolulu CF",
    "url":"http:\/\/www.hcfartcc.net","role":{{"cid":1245046,"name":"Toby Rice","role":"MTR"},
    {"cid":1152158,"name":"Taylor Broad","role":"MTR"},
    {"cid":1147076,"name":"Dave Mayes","role":"ATM"},
    {"cid":1245046,"name":"Toby Rice","role":"DATM"},
    {"cid":1289149,"name":"Israel Reyes","role":"FE"},
    {"cid":1152158,"name":"Taylor Broad","role":"WM"}},
    "stats":{"controllers":19,"pendingTransfers":0},
    "notices":{"D01":{{"id":4,"tmu_facility_id":"D01","priority":2,"message":"Ground hold in effect until 2300Z.",
    "expire_date":"2019-08-01 00:00:00","created_at":"2019-07-18 08:04:36","updated_at":"2019-07-18 08:04:36"}}}}}
     *         }
     *     )
     * )
     */
    public function getFacility($id)
    {
        $facility = Facility::find($id);
        if (!$facility || !$facility->active) {
            return response()->api(
                generate_error("Facility not found or not active", true), 404
            );
        }

        if (\Cache::has("facility.$id.info")) {
            return response()->api(json_decode(\Cache::get("facility.$id.info"), true));
        }

        $data['facility'] = [
            'info'  => $facility->toArray(),
            'roles' => Role::where('facility', $facility->id)->get()->toArray(),
        ];
        $data['stats']['controllers'] = User::where('facility', $id)->count();
        $data['stats']['pendingTransfers'] = Transfer::where('to', $id)->where(
            'status', Transfer::$pending
        )->count();

        $data['notices'] = [];
        foreach (TMUFacility::where('id', $id)->orWhere('parent', $id)->get() as $tmu) {
            if ($tmu->tmuNotices()->count()) {
                $data['notices'][$tmu->id] = $tmu->tmuNotices()->get()->toArray();
            }
        }
        \Cache::put("facility.$id.info", encode_json($data), 60);

        return response()->api($data);
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param                          $id
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @SWG\Put(
     *     path="/facility/{id}",
     *     summary="Update facility information. [Auth]",
     *     description="Update facility information. Requires JWT or Session Cookie. Must be ATM, DATM, or WM.",
     *     produces={"application/json"},
     *     tags={"facility"},
     *     security={"jwt","session"},
     *     @SWG\Parameter(name="id", in="path", description="Facility IATA ID", required=true, type="string"),
     *     @SWG\Parameter(name="url", in="formData", description="Change facility URL", type="string"),
     *     @SWG\Parameter(name="url_dev", in="formData", description="Change facility Dev URL(s)", type="string"),
     *     @SWG\Parameter(name="uls2jwk", in="formData", description="Request new ULS JWK", type="string"),
     *     @SWG\Parameter(name="apiv2jwk", in="formData", description="Request new APIv2 JWK", type="string"),
     *     @SWG\Parameter(name="jwkdev", in="formData", description="Request new testing JWK", type="boolean"),
     *     @SWG\Parameter(name="apikey", in="formData", type="string", description="Request new API Key for facility"),
     *     @SWG\Parameter(name="apikeySandbox", in="formData", type="string", description="Request new Sandbox API Key
    for facility"),
     *     @SWG\Parameter(name="ulsSecret", in="formData", type="string", description="Request new ULS Secret, role
    restricted"),
    @SWG\Parameter(name="ulsReturn", in="formData", type="string", description="Set new ULS return point, role
    restricted"),
     *     @SWG\Parameter(name="ulsDevReturn", in="formData", type="string", description="Set new ULS developmental
    return point"),
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
     *         examples={"application/json":{"status"="OK", "testing"=false}}
     *     )
     * )
     */
    public function putFacility(Request $request, $id)
    {
        $facility = Facility::find($id);
        if (!$facility || !$facility->active) {
            return response()->api(
                generate_error("Facility not found or not active", true), 404
            );
        }

        if (!RoleHelper::has(\Auth::user()->cid, $id, ["ATM", "DATM", "WM"])
            && !RoleHelper::isVATUSAStaff(\Auth::user()->cid)) {
            return response()->api(generate_error("Forbidden", true), 403);
        }

        $data = [];
        if (!isTest()) {
            if ($request->has("url")) {
                if (filter_var(trim($request->input("url")),
                        FILTER_VALIDATE_URL) && !str_contains($request->input('url'), 'vatusa')) {
                    $facility->url = trim($request->input("url"));
                    $facility->save();
                } else {
                    return response()->api(generate_error("Invalid Facility URL",
                        true),
                        409);
                }
                if ($facility->url_dev) {
                    foreach (FacilityHelper::getDevURLs($facility) as $devurl) {
                        if ($devurl == $facility->url) {
                            return response()->api(generate_error("Development URL cannot be the same as the live URL",
                                true),
                                409);
                        }
                    }
                }
            }

            if ($request->has("url_dev")) {
                foreach (FacilityHelper::urlListToArray($request->input("url_dev")) as $devurl) {
                    if ($devurl == $facility->url) {
                        return response()->api(generate_error("Development URL cannot be the same as the live URL",
                            true),
                            409);
                    }
                    if (!filter_var($devurl, FILTER_VALIDATE_URL) || str_contains($devurl, 'vatusa')) {
                        return response()->api(generate_error("Invalid Development URL(s)", true),
                            409);
                    }
                }
                $facility->url_dev = $request->input("url_dev");
                $facility->save();
            }

            //Boolean - development JWK
            $jwkdev = $request->input('jwkdev', false) == "true";

            if ($request->has("ulsV2jwk")) {
                if ($request->input('ulsV2jwk') != 'X') {
                    $key = JWKFactory::createOctKey(
                        env('ULSV2_SIZE', 512),
                        ['alg' => env('ULSV2_ALG', 'HS256'), 'use' => 'sig']
                    );
                } else {
                    $key = "";
                }

                if (!$jwkdev) {
                    $facility->uls_jwk = encode_json($key);
                    $data["uls_jwk"] = encode_json($key);
                } else {
                    $facility->uls_jwk_dev = $key == "" ? $key : encode_json($key);
                    $data["uls_jwk_dev"] = encode_json($key);
                }
                $facility->save();

                //return response()->ok($data);
            }

            if ($request->has("apiV2jwk")) {
                if ($request->input('apiV2jwk') != 'X') {
                    $key = JWKFactory::createOctKey(
                        env('APIV2_SIZE', 1024),
                        ['alg' => env('APIV2_ALG', 'HS256'), 'use' => 'sig']
                    );
                } else {
                    $key = "";
                }

                if (!$jwkdev) {
                    $facility->apiv2_jwk = encode_json($key);
                    $data["api_jwk"] = encode_json($key);
                } else {
                    $facility->apiv2_jwk_dev = $key == "" ? $key : encode_json($key);
                    $data["api_jwk_dev"] = encode_json($key);
                }
                $facility->save();

                //return response()->api($data);
            }

            if ($request->has('apikey')) {
                $data['apikey'] = randomPassword(16);
                $facility->apikey = $data['apikey'];
                $facility->save();
            }

            if ($request->has('apikeySandbox')) {
                $data['apikeySandbox'] = randomPassword(16);
                $facility->api_sandbox_key = $data['apikeySandbox'];
                $facility->save();
            }

            if ($request->has('ulsSecret')) {
                $data['ulsSecret'] = substr(hash('sha512', microtime()), -16);
                $facility->uls_secret = $data['ulsSecret'];
                $facility->save();
            }

            if ($request->has('ulsReturn') && filter_var($request->input("ulsReturn"), FILTER_VALIDATE_URL)) {
                $facility->uls_return = $request->input("ulsReturn");
                $facility->save();
            }

            if ($request->has('ulsDevReturn') && filter_var($request->input("ulsDevReturn"), FILTER_VALIDATE_URL)) {
                $facility->uls_devreturn = $request->input("ulsDevReturn");
                $facility->save();
            }
        }

        return response()->ok($data);
    }

    /**
     *
     * @SWG\Get(
     *     path="/facility/{id}/email/{templateName}",
     *     summary="Get facility's email template. [Key]",
     *     description="Get facility's email template. Requires API Key, Session Cookie (ATM/DATM/TA), or JWT",
     *     produces={"application/json"},
     *     tags={"facility","email"},
     *     @SWG\Parameter(name="id", in="path", description="Facility IATA ID", required=true, type="string"),
     *     @SWG\Parameter(name="templateName", in="path", description="Name of template (welcome, examassigned,
    examfailed, exampassed)",
     *                                          required=true, type="string"),
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
     *         examples={"application/json":{"status"="error","msg"="Facility not found or not active"}},
     *     ),
     * @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(
     *             ref="#/definitions/EmailTemplate"
     *         ),
     *     )
     * )
     * @param \Illuminate\Http\Request $request
     * @param                          $id
     * @param                          $templateName
     *
     * @return \Illuminate\Http\Response
     */
    public
    function getEmailTemplate(
        Request $request,
        $id,
        $templateName
    ) {
        if (!\Auth::check() && !AuthHelper::validApiKeyv2($request->input('apikey', null))) {
            return response()->api(generate_error("Unauthorized"), 401);
        }
        if (\Auth::check() && (!RoleHelper::isSeniorStaff(\Auth::user()->cid, $id, true)
                && !RoleHelper::isVATUSAStaff())
        ) {
            return response()->api(generate_error("Forbidden"), 403);
        }
        $facility = Facility::find($id);
        if (!$facility || $facility->active != 1
            || !in_array(
                $templateName, FacilityHelper::EmailTemplates()
            )
        ) {
            return response()->api(generate_error("Not Found"), 404);
        }

        $template = FacilityHelper::findEmailTemplate($id, $templateName);

        switch ($templateName) {
            case 'exampassed':
                $template['variables'] = [
                    'exam_name',
                    'instructor_name',
                    'correct',
                    'possible',
                    'score',
                    'student_name'
                ];
                break;
            case 'examfailed':
                $template['variables'] = [
                    'exam_name',
                    'instructor_name',
                    'correct',
                    'possible',
                    'score',
                    'student_name',
                    'reassign',
                    'reassign_date'
                ];
                break;
            case 'examassigned':
                $template['variables'] = [
                    'exam_name',
                    'instructor_name',
                    'student_name',
                    'end_date',
                    'cbt_required',
                    'cbt_facility',
                    'cbt_block'
                ];
                break;
            default:
                $template['variables'] = null;
                break;
        }

        return response()->api($template->toArray());
    }

    /**
     *
     * @SWG\Post(
     *     path="/facility/{id}/email/{templateName}",
     *     summary="Modify facility's email template. [Auth]",
     *     description="Modify facility's email template. Requires JWT or Session Cookie (ATM/DATM/TA)",
     *     produces={"application/json"},
     *     tags={"facility","email"},
     *     @SWG\Parameter(name="id", in="query", description="Facility IATA ID", required=true, type="string"),
     *     @SWG\Parameter(name="templateName", in="path", description="Name of template (welcome, examassigned,
     *                                         examfailed, exampassed)", required=true, type="string"),
     * @SWG\Parameter(name="body", in="formData", description="Text of template", required=true, type="string"),
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
     *         description="Not found or not active",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Facility not found or not active"}},
     *     ),
     * @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(
     *             type="object",
     *             @SWG\Property(property="status",type="string"),
     *             @SWG\Property(property="template",type="string"),
     *             @SWG\Property(property="body",type="string"),
     *         ),
     *         examples={"application/json":{"status"="OK", "testing"=false}}
     *     )
     * )
     * @param \Illuminate\Http\Request $request
     * @param                          $id
     * @param                          $templateName
     *
     * @return \Illuminate\Http\Response
     */
    public
    function postEmailTemplate(
        Request $request,
        $id,
        $templateName
    ) {
        if (!\Auth::check()) {
            return response()->api(generate_error("Unauthorized"), 401);
        }
        if (!RoleHelper::isSeniorStaff(\Auth::user()->cid, $id, true)
            && !RoleHelper::isVATUSAStaff()
        ) {
            return response()->api(generate_error("Forbidden"), 403);
        }
        $facility = Facility::find($id);
        if (!$facility || $facility->active != 1
            || !in_array(
                $templateName, FacilityHelper::EmailTemplates()
            )
        ) {
            return response()->api(generate_error("Not Found"), 404);
        }

        if (!isTest()) {
            $template = FacilityHelper::findEmailTemplate($id, $templateName);
            $template->body = preg_replace(array('/<(\?|\%)\=?(php)?/', '/(\%|\?)>/'), array('', ''),
                $request->input("body"));
            $template->save();
        }

        return response()->ok();
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param                          $id
     * @param                          $membership
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @SWG\Get(
     *     path="/facility/{id}/roster/{membership}",
     *     summary="Get facility roster.",
     *     description="Get facility staff. Email field requires authentication as senior staff.
    Broadcast opt-in status requires API key or staff member authentication. Prevent Staff Assignment field requires
    authentication as senior staff.", produces={"application/json"}, tags={"facility"},
     * @SWG\Parameter(name="id", in="query", description="Facility IATA ID", required=true, type="string"),
     * @SWG\Parameter(name="membership", in="query", description="Membership type (home, visit, both)", default="home", type="string"),
     * @SWG\Response(
     *         response="400",
     *         description="Malformed request, invalid role parameter",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Malformed request"}},
     *     ),
     * @SWG\Response(
     *         response="404",
     *         description="Not found or not active",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Facility not found or not active"}},
     *     ),
     * @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(
     *             type="object",
     *             @SWG\Property(property="cid", type="integer"),
     *             @SWG\Property(property="fname", type="string", description="First name"),
     *             @SWG\Property(property="lname", type="string", description="Last name"),
     *             @SWG\Property(property="email", type="string", description="Email address of user, will be null if API Key or necessary roles are not available (ATM, DATM, TA, WM, INS)"),
     *             @SWG\Property(property="facility", type="string", description="Facility ID"),
     *             @SWG\Property(property="rating", type="integer", description="Rating based off array where 1=OBS, S1, S2, S3, C1, C2, C3, I1, I2, I3, SUP, ADM"),
     *             @SWG\Property(property="rating_short", type="string", description="String representation of rating"),
     *             @SWG\Property(property="created_at", type="string", description="Date added to database"),
     *             @SWG\Property(property="updated_at", type="string"),
     *             @SWG\Property(property="flag_needbasic", type="integer", description="1 needs basic exam"),
     *             @SWG\Property(property="flag_xferOverride", type="integer", description="Has approved transfer override"),
     *             @SWG\Property(property="flag_broadcastOptedIn", type="integer", description="Has opted in to receiving broadcast emails"),
     *             @SWG\Property(property="flag_preventStaffAssign", type="integer", description="Ineligible for staff role assignment"),
     *             @SWG\Property(property="facility_join", type="string", description="Date joined facility (YYYY-mm-dd hh:mm:ss)"),
     *             @SWG\Property(property="promotion_eligible", type="boolean", description="Is member eligible for promotion?"),
     *             @SWG\Property(property="transfer_eligible", type="boolean", description="Is member is eligible for transfer?"),
     *             @SWG\Property(property="last_promotion", type="string", description="Date last promoted"),
     *             @SWG\Property(property="flag_homecontroller", type="boolean", description="1-Belongs to VATUSA"),
     *             @SWG\Property(property="lastactivity", type="string", description="Date last seen on website"),
     *             @SWG\Property(property="isMentor", type="boolean", description="Has Mentor role"),
     *             @SWG\Property(property="isSupIns", type="boolean", description="Is a SUP and has INS role"),
     *             @SWG\Property(property="membership", type="string", description="'Home' or 'visit' depending on facility membership."),
     *             @SWG\Property(property="roles", type="array",
     *                 @SWG\Items(type="object",
     *                     @SWG\Property(property="facility", type="string"),
     *                     @SWG\Property(property="role", type="string")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public
    function getRoster(
        Request $request,
        $id,
        $membership = 'home'
    ) {
        $facility = Facility::find($id);
        if (!$facility || $facility->active != 1) {
            return response()->api(generate_error("Not found"), 404);
        }

        $hasAPIKey = AuthHelper::validApiKeyv2($request->input('apikey', null), $id);
        $isFacStaff = \Auth::check() && RoleHelper::isFacilityStaff(\Auth::user()->cid, \Auth::user()->facility);
        $isSeniorStaff = \Auth::check() && RoleHelper::isSeniorStaff(\Auth::user()->cid, \Auth::user()->facility);

        $rosterArr = [];

        if ($membership == 'both')
        {
            $home = $facility->members;
            $visiting = $facility->visitors();
            $roster = $home->merge($visiting);
        }
        else if ($membership == 'home')
        {
            $roster = $facility->members;
        }
        else if ($membership == 'visit')
        {
            $roster = $facility->visitors();
        }
        else
        {
            return response()->api(generate_error("Malformed request"), 400);
        }
        $i = 0;
        foreach ($roster as $member) {
            $rosterArr[$i] = $member;
            if (!$hasAPIKey && !$isFacStaff) {
                $rosterArr[$i]['flag_broadcastOptedIn'] = null;
                $rosterArr[$i]['email'] = null;
            }
            if (!$isSeniorStaff) {
                //Senior Staff Only
                $rosterArr[$i]['flag_preventStaffAssign'] = null;
            }


            //Add rating_short property
            $rosterArr[$i]['rating_short'] = RatingHelper::intToShort($member->rating);

            //Is Mentor
            $rosterArr[$i]['isMentor'] = $member->roles->where("facility", $id)
                    ->where("role", "MTR")->count() > 0;

            //Has Ins Perms
            $rosterArr[$i]['isSupIns'] = $roster[$i]['rating_short'] === "SUP" &&
                $member->roles->where("facility", $id)
                    ->where("role", "INS")->count() > 0;

            //Last promotion date
            $last_promotion = $member->lastPromotion();
            if ($last_promotion) {
                $rosterArr[$i]['last_promotion'] = $last_promotion->created_at;
            } else {
                $rosterArr[$i]['last_promotion'] = null;
            }

            //Membership
            if ($member->facility == $id) {
                $rosterArr[$i]['membership'] = 'home';
            } else {
                $rosterArr[$i]['membership'] = 'visit';
                $rosterArr[$i]['facility_join'] = Visit::where('facility', $id)
                                                        ->where('cid', $member->cid)->first()->updated_at;
            }

            $i++;
        }

        return response()->api($rosterArr);
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param string                   $id
     * @param integer                  $cid
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @SWG\Post(
     *     path="/facility/{id}/roster/manageVisitor/{cid}",
     *     summary="Add member to visiting roster. [Auth]",
     *     description="Add member to visiting roster.  JWT or Session Cookie required (required role: ATM,
    DATM, VATUSA STAFF)",
     * produces={"application/json"},
     * tags={"facility"},
     * security={"jwt","session"},
     * @SWG\Parameter(name="id", in="query", description="Facility IATA ID", required=true, type="string"),
     * @SWG\Parameter(name="cid", in="query", description="CID of controller", required=true, type="integer"),
     * @SWG\Response(
     *         response="401",
     *         description="Unauthorized",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Unauthorized"}},
     *     ),
     * @SWG\Response(
     *         response="403",
     *         description="Forbidden -- needs to have role of ATM, DATM or VATUSA Division staff member",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","message"="Forbidden"}},
     *     ),
     * @SWG\Response(
     *         response="404",
     *         description="Not found or not active",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Facility not found or not active"}},
     *     ),
     * @SWG\Response(
     *         response="412",
     *         description="User is already visiting this facility",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="User is already visiting this facility"}},
     *     ),
     * @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(ref="#/definitions/OK"),
     *         examples={"application/json":{"status"="OK", "testing"=false}}
     *     )
     * )
     */
    public
    function addVisitor(
        Request $request,
        string $id,
        int $cid
    ) {
        // Checks if facility exists and is active
        $facility = Facility::find($id);
        if (!$facility || !$facility->active) {
            return response()->api(
                generate_error("Facility not found or not active"), 404);
        }

        // Checks if requesting user is VATUSA or senior staff
        if (!RoleHelper::isVATUSAStaff() && !RoleHelper::isSeniorStaff(\Auth::user()->cid, $id, false)) {
            return response()->api(generate_error("Forbidden"), 403);
        }

        // Checks if user with specified cid exists
        $user = User::where('cid', $cid)->first();
        if (!$user) {
            return response()->api(
                generate_error("User not found"), 404
            );
        }

        // Checks if user is a member at the specified facility
        if ($user->facility == $id) {
            return response()->api(
                generate_error("User is a member at this facility"), 400
            );
        }

        // Checks if the visit already exists
        $visit = Visit::where('cid', $cid)->where('facility', $id)->first();
        if ($visit) {
            // If visit is inactive it will reactivate it
            if (!$visit->active) {
                $visit->active = 1;
                $visit->save();
            } else {
                return response()->api(
                    generate_error("User is already visiting this facility"), 400
                );
            }
        } else {
            $visitor = new Visit();
            $visitor->cid = $user->cid;
            $visitor->facility = $id;
            $visitor->active = 1;
            $visitor->save();
        }

        return response()->ok();
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param string                   $id
     * @param integer                  $cid
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @SWG\Delete(
     *     path="/facility/{id}/roster/manageVisitor/{cid}",
     *     summary="Delete member from visiting roster. [Auth]",
     *     description="Delete member from visiting roster.  JWT or Session Cookie required (required role: ATM,
    DATM, VATUSA STAFF)",
     * produces={"application/json"},
     * tags={"facility"},
     * security={"jwt","session"},
     * @SWG\Parameter(name="id", in="query", description="Facility IATA ID", required=true, type="string"),
     * @SWG\Parameter(name="cid", in="query", description="CID of controller", required=true, type="integer"),
     * @SWG\Parameter(name="reason", in="formData", description="Reason for deletion", required=true, type="string"),
     * @SWG\Response(
     *         response="400",
     *         description="Malformed request, missing required parameter",
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
     *         description="Forbidden -- needs to have role of ATM, DATM or VATUSA Division staff member",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","message"="Forbidden"}},
     *     ),
     * @SWG\Response(
     *         response="404",
     *         description="Not found or not active",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Facility not found or not active"}},
     *     ),
     * @SWG\Response(
     *         response="412",
     *         description="User is not visiting this facility",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="User is not visiting this facility"}},
     *     ),
     * @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(ref="#/definitions/OK"),
     *         examples={"application/json":{"status"="OK", "testing"=false}}
     *     )
     * )
     */
    public
    function removeVisitor(
        Request $request,
        string $id,
        int $cid
    ) {
        // Checks if facility exists and is active
        $facility = Facility::find($id);
        if (!$facility || !$facility->active) {
            return response()->api(
                generate_error("Facility not found or not active"), 404);
        }

        // Checks if requesting user is VATUSA or senior staff
        if (!RoleHelper::isVATUSAStaff() && !RoleHelper::isSeniorStaff(\Auth::user()->cid, $id, false)) {
            return response()->api(generate_error("Forbidden"), 403);
        }

        // Checks if user with specified cid exists
        $user = User::where('cid', $cid)->first();
        if (!$user || $user->facility != $facility->id) {
            return response()->api(
                generate_error("User not found or not in facility"), 404
            );
        }

        // Checks if request has a reason attached
        if (!$request->has("reason") || !$request->filled("reason")) {
            return response()->api(generate_error("Malformed request"), 400);
        }

        // Checks if user is a member at the specified facility
        $visit = Visit::where('cid', $cid)->where('facility', $id)->first();
        if (!$visit) {
            return response()->api(
                generate_error("User is not visiting this facility"), 412
            );
        }

        $visit->active = 0;
        $visit->save();

        return response()->ok();
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param string                   $id
     * @param integer                  $cid
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @SWG\Delete(
     *     path="/facility/{id}/roster/{cid}",
     *     summary="Delete member from facility roster. [Auth]",
     *     description="Delete member from facility roster.  JWT or Session Cookie required (required role: ATM,
    DATM, VATUSA STAFF)",
     * produces={"application/json"},
     * tags={"facility"},
     * security={"jwt","session"},
     * @SWG\Parameter(name="id", in="query", description="Facility IATA ID", required=true, type="string"),
     * @SWG\Parameter(name="cid", in="query", description="CID of controller", required=true, type="integer"),
     * @SWG\Parameter(name="reason", in="formData", description="Reason for deletion", required=true, type="string"),
     * @SWG\Response(
     *         response="400",
     *         description="Malformed request, missing required parameter",
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
     *         description="Forbidden -- needs to have role of ATM, DATM or VATUSA Division staff member",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","message"="Forbidden"}},
     *     ),
     * @SWG\Response(
     *         response="404",
     *         description="Not found or not active",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Facility not found or not active"}},
     *     ),
     * @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(ref="#/definitions/OK"),
     *         examples={"application/json":{"status"="OK", "testing"=false}}
     *     )
     * )
     */
    public
    function deleteRoster(
        Request $request,
        string $id,
        int $cid
    ) {
        $facility = Facility::find($id);
        if (!$facility || !$facility->active) {
            return response()->api(
                generate_error("Facility not found or not active"), 404);
        }

        if (!RoleHelper::isVATUSAStaff() && !RoleHelper::isSeniorStaff(\Auth::user()->cid, $id, false)) {
            return response()->api(generate_error("Forbidden"), 403);
        }

        $user = User::where('cid', $cid)->first();
        if (!$user || $user->facility != $facility->id) {
            return response()->api(
                generate_error("User not found or not in facility"), 404
            );
        }

        if (!$request->has("reason") || !$request->filled("reason")) {
            return response()->api(generate_error("Malformed request"), 400);
        }

        if (!isTest()) {
            $user->removeFromFacility(
                \Auth::user()->cid, $request->input("reason")
            );
        }

        return response()->ok();
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param string                   $id
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @SWG\Get(
     *     path="/facility/{id}/transfers",
     *     summary="Get pending transfers. [Key]",
     *     description="Get pending transfers. Requires API Key, Session Cookie, or JWT",
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
     *             type="object",
     *             @SWG\Property(property="status", type="string"),
     *             @SWG\Property(property="transfers", type="array",
     *                 @SWG\Items(
     *                     type="object",
     *                     @SWG\Property(property="id", type="integer", description="Transfer ID"),
     *                     @SWG\Property(property="cid", type="integer", description="VATSIM ID"),
     *                     @SWG\Property(property="fname", type="string", description="First name"),
     *                     @SWG\Property(property="lname", type="string", description="Last name"),
     *                     @SWG\Property(property="email", type="string", description="Email, if authenticated as staff
    member and/or api key is present."),
     *                     @SWG\Property(property="reason", type="string", description="Transfer reason; must be
     *                                                      authenticated as senior staff."),
     *                     @SWG\Property(property="fromFac", type="array",
     *                         @SWG\Items(
     *                             type="object",
     *                             @SWG\Property(property="id", type="integer", description="Facility ID (ex. ZSE)"),
     *                             @SWG\Property(property="name", type="integer", description="Facility Name (ex.
    Seattle ARTCC)")
     *                         )
     *                     ),
     *                     @SWG\Property(property="rating", type="string", description="Short string rating (S1, S2)"),
     *                     @SWG\Property(property="intRating", type="integer", description="Numeric rating (OBS = 1,
    etc)"),
     *                     @SWG\Property(property="date", type="string", description="Date transfer submitted
    (YYYY-MM-DD)"),
     *                 ),
     *             ),
     *         ),
     *         examples={"application/json":{"status":"OK","transfers":{"id":5606,"cid":1275302,"fname":"Blake",
    "lname":"Nahin","email":null,"reason":"Only one class B? Too easy. I want something harder, like ZTL.",
    "rating":"C1","intRating":5,"date":"2014-12-19","fromFac":{"id":"ZSE","name":"Seattle ARTCC"}}}}
     *     )
     * )
     */
    public
    function getTransfers(
        Request $request,
        string $id
    ) {
        $facility = Facility::find($id);
        if (!$facility || !$facility->active) {
            return response()->api(
                generate_error("Facility not found or not active"), 404
            );
        }

        if (!AuthHelper::validApiKeyv2($request->input('apikey', null)) && !\Auth::check()) {
            return response()->api(generate_error("Unauthorized"), 401);
        }

        if (!AuthHelper::validApiKeyv2($request->input('apikey', null))
            && !RoleHelper::isFacilityStaff(\Auth::user()->cid, $id)
            && !RoleHelper::isVATUSAStaff(\Auth::user()->cid)
        ) {
            return response()->api(generate_error("Forbidden"), 403);
        }

        $transfers = Transfer::where("to", $facility->id)->where(
            "status", Transfer::$pending
        )->get();
        $data = [];
        foreach ($transfers as $transfer) {
            $data[] = [
                'id'        => $transfer->id,
                'cid'       => $transfer->cid,
                'fname'     => $transfer->user->fname,
                'lname'     => $transfer->user->lname,
                'email'     => (AuthHelper::validApiKeyv2($request->input('apikey',
                        null)) || (\Auth::check() && RoleHelper::isFacilityStaff(\Auth::user()->cid)))
                    ? $transfer->user->email : null,
                'reason'    => (\Auth::check() && RoleHelper::isSeniorStaff(\Auth::user()->cid)) ? $transfer->reason : null,
                'fromFac'   => [
                    'id'   => $transfer->from,
                    'name' => $transfer->fromFac->name
                ],
                'rating'    => RatingHelper::intToShort($transfer->user->rating),
                'intRating' => $transfer->user->rating,
                'date'      => $transfer->created_at->format('Y-m-d')
            ];
        }

        return response()->ok(['transfers' => $data]);
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param string                   $id
     * @param int                      $transferId
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @SWG\Put(
     *     path="/facility/{id}/transfers/{transferId}",
     *     summary="Modify transfer request.  [Auth]",
     *     description="Modify transfer request.  JWT or Session cookie required. (required role: ATM, DATM,
    VATUSA STAFF)", produces={"application/json"}, tags={"facility"}, security={"jwt","session"},
     * @SWG\Parameter(name="id", in="query", description="Facility IATA ID", required=true, type="string"),
     * @SWG\Parameter(name="transferId", in="query", description="Transfer ID", type="integer", required=true),
     * @SWG\Parameter(name="action", in="formData", type="string", required=true, enum={"approve","reject"},
     *                                   description="Action to take on transfer request. Valid values:
    approve,reject"),
     * @SWG\Parameter(name="reason", in="formData", type="string", description="Reason for transfer request rejection
    [required for rejections]"),
     * @SWG\Response(
     *         response="400",
     *         description="Malformed request, missing required parameter",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Malformed request"}},
     *     ),
     * @SWG\Response(
     *         response="403",
     *         description="Forbidden -- needs to be a staff member, other than mentor",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","message"="Forbidden"}},
     *     ),
     * @SWG\Response(
     *         response="404",
     *         description="Not found or not active",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Facility not found or not active"}},
     *     ),
     * @SWG\Response(
     *         response="410",
     *         description="Gone",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Transfer is not pending"}},
     *     ),
     * @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(ref="#/definitions/OK"),
     *         examples={"application/json":{"status"="OK", "testing"=false}}
     *     )
     * )
     */
    public
    function putTransfer(
        Request $request,
        string $id,
        int $transferId
    ) {
        $facility = Facility::find($id);
        if (!$facility || !$facility->active) {
            return response()->api(
                generate_error("Facility not found or not active"), 404
            );
        }

        if (!\Auth::check()) {
            return response()->api(generate_error("Unauthorized"), 401);
        }

        if (!RoleHelper::isSeniorStaff(\Auth::user()->cid, $facility->id, false)
            && !RoleHelper::isVATUSAStaff(\Auth::user()->cid)
        ) {
            return response()->api(generate_error("Forbidden"), 403);
        }

        $transfer = Transfer::find($transferId);
        if (!$transfer) {
            return response()->api(
                generate_error("Transfer request not found"), 404
            );
        }

        if ($transfer->status !== Transfer::$pending) {
            return response()->api(
                generate_error("Transfer is not pending"), 410
            );
        }

        if ($transfer->to !== $facility->id) {
            return response()->api(generate_error("Forbidden"), 403);
        }

        if (!in_array($request->input("action"), ["accept", "reject"])
            || ($request->input("action") === "reject"
                && !$request->filled(
                    "reason"
                ))
        ) {
            return response()->api(generate_error("Malformed request"), 400);
        }

        if (!isTest()) {
            if ($request->input("action") === "accept") {
                $transfer->accept(\Auth::user()->cid);
            } else {
                $transfer->reject(\Auth::user()->cid, $request->input("reason"));
            }
        }

        return response()->ok();
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param string                   $id
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @SWG\Get(
     *     path="/facility/{id}/ulsReturns",
     *     summary="Get ULS return paths. [Key]",
     *     description="Get ULS return paths. Requires API Key, Session Cookie, or JWT",
     *     produces={"application/json"},
     *     tags={"facility"},
     *     security={"jwt","session","apikey"},
     * @SWG\Response(
     *         response="401",
     *         description="Unauthorized",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Unauthorized"}},
     *     ),
     * @SWG\Response(
     *         response="403",
     *         description="Forbidden -- needs to be a staff member, other than mentor",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","message"="Forbidden"}},
     *     ),
     * @SWG\Response(
     *         response="404",
     *         description="Not found or not active",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Facility not found or not active"}},
     *     ),
     * @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(
     *             type="object",
     *             @SWG\Property(property="status", type="string"),
     *             @SWG\Property(property="paths", type="array",
     *                 @SWG\Items(
     *                     type="object",
     *                 @SWG\Property(property="id", type="integer", description="Path DB ID"),
     *                     @SWG\Property(property="order", type="integer", description="ID used in ULS query"),
     *                     @SWG\Property(property="facility_id", type="string", description="Facility assocaited with
    path"),
     *                     @SWG\Property(property="url", type="string", description="Return URL")
     *                 ),
     *             ),
     *         ),
     *         examples={"application/json":{"status":"OK","paths":{"id":1,"order":1,"facility_id":"ZSE","url":"https://zseartcc.org/uls-return/"}}}
     *     )
     * )
     */
    public
    function getUlsReturns(
        Request $request,
        string $id
    ) {
        $facility = Facility::find($id);
        if (!$facility || !$facility->active) {
            return response()->api(
                generate_error("Facility not found or not active"), 404
            );
        }
        if (!AuthHelper::validApiKeyv2($request->input('apikey', null), $id) && !\Auth::check()) {
            return response()->api(generate_error("Unauthorized"), 401);
        }

        if (!AuthHelper::validApiKeyv2($request->input('apikey', null), $id)
            && !RoleHelper::isFacilityStaff(\Auth::user()->cid)
            && !RoleHelper::isVATUSAStaff(\Auth::user()->cid)
        ) {
            return response()->api(generate_error("Forbidden"), 403);
        }

        return response()->ok(['paths' => $facility->returnPaths]);
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param string                   $id
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @SWG\Post(
     *     path="/facility/{id}/ulsReturns",
     *     summary="Add ULS return path. [Key]",
     *     description="Add new ULS return path. Requires API Key, Session Cookie, or JWT",
     *     produces={"application/json"},
     *     tags={"facility"},
     *     security={"jwt","session","apikey"},
     * @SWG\Parameter(name="id", in="query", description="Facility IATA ID", required=true, type="string"),
     * @SWG\Parameter(name="order", in="formData", description="Order number, used in ULS query", type="integer",
     *                              required=true),
     * @SWG\Parameter(name="url", in="formData", type="string", required=true, description="Return URL"),
     *
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
     *    @SWG\Response(
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
     * @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(
     *             type="object",
     *             @SWG\Property(property="status", type="string")
     *         )
     *     )
     *   )
     * )
     */
    public
    function addUlsReturn(
        Request $request,
        string $id
    ) {
        $facility = Facility::find($id);
        if (!$facility || !$facility->active) {
            return response()->api(
                generate_error("Facility not found or not active"), 404
            );
        }
        if (!AuthHelper::validApiKeyv2($request->input('apikey', null), $id)
            && !\Auth::check()) {
            return response()->api(generate_error("Unauthorized"), 401);
        }

        if (!AuthHelper::validApiKeyv2($request->input('apikey', null), $id)
            && !RoleHelper::isFacilityStaff(\Auth::user()->cid)
            && !RoleHelper::isVATUSAStaff(\Auth::user()->cid)
        ) {
            return response()->api(generate_error("Forbidden"), 403);
        }

        $order = $request->input('order', null);
        $url = $request->input('url', null);

        if (!$order) {
            return response()->api(generate_error("Malformed request, missing order ID"), 400);
        }


        if ($facility->returnPaths()->where('order', $order)->exists()) {
            //Add to end
            $order = $facility->returnPaths()
                    ->orderBy('order', 'DESC')->pluck('order')->first() + 1;
        }

        if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
            return response()->api(generate_error("Malformed request, invalid URL"), 400);
        }

        if (!isTest()) {
            $facility->returnPaths()->create([
                'order' => $order,
                'url'   => $url
            ]);
        }

        return response()->ok();
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param string                   $id
     *
     * @param int                      $order
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @SWG\Delete(
     *     path="/facility/{id}/ulsReturns/{order}",
     *     summary="Remove ULS return path. [Key]",
     *     description="Remove ULS return path. Requires API Key, Session Cookie, or JWT",
     *     produces={"application/json"},
     *     tags={"facility"},
     *     security={"jwt","session","apikey"},
     * @SWG\Parameter(name="id", in="query", description="Facility IATA ID", required=true, type="string"),
     * @SWG\Parameter(name="order", in="query", description="Order number, used in ULS query", type="integer",
     *                              required=true),
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
     *    @SWG\Response(
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
     * @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(
     *             type="object",
     *             @SWG\Property(property="status", type="string")
     *         )
     *     )
     *   )
     * )
     */
    public
    function removeUlsReturn(
        Request $request,
        string $id,
        int $order
    ) {
        $facility = Facility::find($id);
        if (!$facility || !$facility->active) {
            return response()->api(
                generate_error("Facility not found or not active"), 404
            );
        }
        if (!AuthHelper::validApiKeyv2($request->input('apikey', null), $id) && !\Auth::check()) {
            return response()->api(generate_error("Unauthorized"), 401);
        }

        if (!AuthHelper::validApiKeyv2($request->input('apikey', null), $id)
            && !RoleHelper::isFacilityStaff(\Auth::user()->cid)
            && !RoleHelper::isVATUSAStaff(\Auth::user()->cid)
        ) {
            return response()->api(generate_error("Forbidden"), 403);
        }

        if (!$order) {
            return response()->api(generate_error("Malformed request, missing order ID"), 400);
        }

        if (!$facility->returnPaths()->where('order', $order)->exists()) {
            return response()->api(generate_error("Return path not found"), 404);
        }

        if (!isTest()) {
            $facility->returnPaths()->where('order', $order)->delete();

            //Shift order IDs down
            foreach ($facility->returnPaths()->where('order', '>', $order)->get() as $path) {
                $path->order--;
                $path->save();
            }
        }

        return response()->ok();
    }


    /**
     * @param \Illuminate\Http\Request $request
     * @param string                   $id
     *
     * @param int                      $order
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @SWG\Put(
     *     path="/facility/{id}/ulsReturns",
     *     summary="Edit ULS return path. [Key]",
     *     description="Edit ULS return path. Requires API Key, Session Cookie, or JWT",
     *     produces={"application/json"},
     *     tags={"facility"},
     *     security={"jwt","session","apikey"},
     * @SWG\Parameter(name="id", in="query", description="Facility IATA ID", required=true, type="string"),
     * @SWG\Parameter(name="url", in="formData", type="string", required=true, description="Return URL"),
     *
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
     *    @SWG\Response(
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
     * @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(
     *             type="object",
     *             @SWG\Property(property="status", type="string")
     *         )
     *     )
     *   )
     * )
     */
    public
    function putUlsReturn(
        Request $request,
        string $id,
        int $order
    ) {
        $facility = Facility::find($id);
        if (!$facility || !$facility->active) {
            return response()->api(
                generate_error("Facility not found or not active"), 404
            );
        }
        if (!AuthHelper::validApiKeyv2($request->input('apikey', null), $id) && !\Auth::check()) {
            return response()->api(generate_error("Unauthorized"), 401);
        }

        if (!AuthHelper::validApiKeyv2($request->input('apikey', null), $id)
            && !RoleHelper::isFacilityStaff(\Auth::user()->cid)
            && !RoleHelper::isVATUSAStaff(\Auth::user()->cid)
        ) {
            return response()->api(generate_error("Forbidden"), 403);
        }

        $url = $request->input('url', null);

        if (!$order) {
            return response()->api(generate_error("Malformed request, missing order ID"), 400);
        }

        if (!$facility->returnPaths()->where('order', $order)->exists()) {
            return response()->api(generate_error("Return path not found"), 404);
        }

        if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
            return response()->api(generate_error("Malformed request, invalid URL"), 400);
        }

        if (!isTest()) {
            $path = ReturnPaths::where([
                'facility_id' => $id,
                'order'       => $order,
            ])->first();
            $path->url = $url;
            $path->save();
        }

        return response()->ok();
    }
}
