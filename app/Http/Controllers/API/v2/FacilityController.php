<?php

namespace App\Http\Controllers\API\v2;

use App\Helpers\AuthHelper;
use App\Helpers\FacilityHelper;
use App\Helpers\EmailHelper;
use App\Helpers\RatingHelper;
use App\Helpers\RoleHelper;
use App\Role;
use App\TMUFacility;
use App\Transfer;
use App\User;
use App\Visit;
use Auth;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Facility;
use Illuminate\Support\Facades\Cache;
use Jose\Component\KeyManagement\JWKFactory;
use Hidehalo\Nanoid\Client as NanoidClient;

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
     * @OA\Get(
     *     path="/facility",
     *     summary="Get list of VATUSA facilities.",
     *     description="Get list of VATUSA facilities.",
     *     tags={"facility"},
     *     @OA\Response(
     *         response="200",
     *         description="OK",
     *         @OA\Schema(
     *             type="array",
     *             @OA\Items(
     *                 ref="#/components/schemas/Facility"
     *             ),
     *         ),
     *     )
     * )
     */
    public function getIndex()
    {
        $data = Facility::where("active", 1)->get()->toArray();

        return response()->api($data);
    }

    /**
     * @param $id
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @OA\Get(
     *     path="/facility/{id}",
     *     summary="Get facility information.",
     *     description="Get facility information.",
     *     tags={"facility"},
     *     @OA\Parameter(name="id", in="query", description="Facility IATA ID", required=true, @OA\Schema(type="string")),
     *     @OA\Response(
     *         response="404",
     *         description="Not found or not active",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="OK",
     *         @OA\Schema(
     *             type="object",
     *             @OA\Property(property="facility", ref="#/components/schemas/Facility"),
     *             @OA\Property(property="roles", type="array",
     *                 @OA\Items(
     *                     ref="#/components/schemas/Role",
     *                 ),
     *             ),
     *             @OA\Property(
     *                 property="stats",
     *                 type="object",
     *                 @OA\Property(property="controllers", @OA\Schema(type="integer"), description="Number of controllers on
    facility roster"),
     *                 @OA\Property(property="pendingTransfers", @OA\Schema(type="integer"), description="Number of pending
    transfers to facility"),
     *             ),
     *         ),
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

        if (Cache::has("facility.$id.info")) {
            return response()->api(json_decode(Cache::get("facility.$id.info"), true));
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
        Cache::put("facility.$id.info", encode_json($data), 60 * 60);

        return response()->api($data);
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param                          $id
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @OA\Put(
     *     path="/facility/{id}",
     *     summary="Update facility information. [Auth]",
     *     description="Update facility information. Requires JWT or Session Cookie. Must be ATM, DATM, or WM.",
     *     tags={"facility"},
     *     security={"jwt"},
     *     @OA\Parameter(name="id", in="path", description="Facility IATA ID", required=true, @OA\Schema(type="string")),
     * @OA\RequestBody(
     * @OA\MediaType(
     *   mediaType="application/x-www-form-urlencoded",
     *   @OA\Schema(
     *   @OA\Parameter(name="url",description="Change facility URL", @OA\Schema(type="string")),
     *   @OA\Parameter(name="url_dev",description="Change facility Dev URL(s)", @OA\Schema(type="string")),
     *   @OA\Parameter(name="apiv2jwk",description="Request new APIv2 JWK", @OA\Schema(type="string")),
     *   @OA\Parameter(name="jwkdev",description="Request new testing JWK", @OA\Schema(type="string")),
     *   @OA\Parameter(name="apikey",description="Request new API Key for facility", @OA\Schema(type="string")),
     *   @OA\Parameter(name="apikeySandbox",description="Request new Sandbox API Key for facility", @OA\Schema(type="string")),
     *  )
     * )
     * ),
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
     *         description="Not found or not active",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="OK",
     *         @OA\Schema(
     *             type="object",
     *             @OA\Property(property="status",type="string"),
     *             @OA\Property(property="apikey",type="string"),
     *             @OA\Property(property="apikeySandbox",type="string"),
     *         ),
     *         
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

        if (!RoleHelper::has(Auth::user()->cid, $id, ["ATM", "DATM", "WM"])
            && !RoleHelper::isVATUSAStaff(Auth::user()->cid)) {
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
        }

        return response()->ok($data);
    }

    /**
     *
     * @OA\Get(
     *     path="/facility/{id}/email/{templateName}",
     *     summary="Get facility's email template. [Key]",
     *     description="Get facility's email template. Requires API Key, Session Cookie (ATM/DATM/TA), or JWT",
     *     tags={"facility","email"},
     *     @OA\Parameter(name="id", in="path", description="Facility IATA ID", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="templateName", in="path", description="Name of template (welcome, examassigned,
    examfailed, exampassed)",
     *                                          required=true, @OA\Schema(type="string")),
     * @OA\Response(
     *         response="401",
     *         description="Unauthorized",
     *         @OA\Schema(ref="#/components/schemas/error"), 
     *     ),
     * @OA\Response(
     *         response="403",
     *         description="Forbidden",
     *         @OA\Schema(ref="#/components/schemas/error"), 
     *     ),
     * @OA\Response(
     *         response="404",
     *         description="Not found",
     *         @OA\Schema(ref="#/components/schemas/error"), 
     *     ),
     * @OA\Response(
     *         response="200",
     *         description="OK",
     *         @OA\Schema(
     *             ref="#/components/schemas/EmailTemplate"
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
        if (!Auth::check() && !AuthHelper::validApiKeyv2($request->input('apikey', null))) {
            return response()->api(generate_error("Unauthorized"), 401);
        }
        if (Auth::check() && (!RoleHelper::isSeniorStaff(Auth::user()->cid, $id, true)
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
     * @OA\Post(
     *     path="/facility/{id}/email/{templateName}",
     *     summary="Modify facility's email template. [Auth]",
     *     description="Modify facility's email template. Requires JWT or Session Cookie (ATM/DATM/TA)",
     *     tags={"facility","email"},
     *     @OA\Parameter(name="id", in="query", description="Facility IATA ID", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="templateName", in="path", description="Name of template (welcome, examassigned,
    examfailed, exampassed)", required=true, @OA\Schema(type="string")),
     * @OA\RequestBody(
     * @OA\MediaType(
     *  mediaType="application/x-www-form-urlencoded",
     * @OA\Schema(
     * @OA\Parameter(name="body", description="Text of template", required=true, @OA\Schema(type="string")),
     * )
     * )
     * ),
     * @OA\Response(
     *         response="401",
     *         description="Unauthorized",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *     ),
     * @OA\Response(
     *         response="403",
     *         description="Forbidden",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     * @OA\Response(
     *         response="404",
     *         description="Not found or not active",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *     ),
     * @OA\Response(
     *         response="200",
     *         description="OK",
     *         @OA\Schema(
     *             type="object",
     *             @OA\Property(property="status",type="string"),
     *             @OA\Property(property="template",type="string"),
     *             @OA\Property(property="body",type="string"),
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
    function postEmailTemplate(
        Request $request,
        $id,
        $templateName
    ) {
        if (!Auth::check()) {
            return response()->api(generate_error("Unauthorized"), 401);
        }
        if (!RoleHelper::isSeniorStaff(Auth::user()->cid, $id, true)
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
     * @OA\Get(
     *     path="/facility/{id}/roster/{membership}",
     *     summary="Get facility roster.",
     *     description="Get facility staff. Email field requires authentication as senior staff.
    Broadcast opt-in status requires API key or staff member authentication. Prevent Staff Assignment field requires
    authentication as senior staff.",  tags={"facility"},
     * @OA\Parameter(name="id", in="query", description="Facility IATA ID", required=true, @OA\Schema(type="string")),
     * @OA\Parameter(name="membership", in="query", description="Membership type (home, visit, both) - defaults to
     * home", @OA\Schema(type="string")),
     * @OA\Response(
     *         response="400",
     *         description="Malformed request, invalid role parameter",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     * @OA\Response(
     *         response="404",
     *         description="Not found or not active",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     * @OA\Response(
     *         response="200",
     *         description="OK",
     *         @OA\Schema(
     *             type="object",
     *             @OA\Property(property="cid", @OA\Schema(type="integer")),
     *             @OA\Property(property="fname", @OA\Schema(type="string"), description="First name"),
     *             @OA\Property(property="lname", @OA\Schema(type="string"), description="Last name"),
     *             @OA\Property(property="email", @OA\Schema(type="string"), description="Email address of user, will be null if
    API Key or necessary roles are not available (ATM, DATM, TA, WM,
    INS)"),
     *             @OA\Property(property="facility", @OA\Schema(type="string"), description="Facility ID"),
     *             @OA\Property(property="rating", @OA\Schema(type="integer"), description="Rating based off array where 1=OBS,
    S1, S2, S3, C1, C2, C3, I1, I2, I3, SUP, ADM"),
     *             @OA\Property(property="rating_short", @OA\Schema(type="string"), description="String representation of
    rating"),
     *             @OA\Property(property="created_at", @OA\Schema(type="string"), description="Date added to database"),
     *             @OA\Property(property="updated_at", @OA\Schema(type="string")),
     *             @OA\Property(property="flag_needbasic", @OA\Schema(type="integer"), description="1 needs basic exam"),
     *             @OA\Property(property="flag_xferOverride", @OA\Schema(type="integer"), description="Has approved transfer
    override"),
     *             @OA\Property(property="flag_broadcastOptedIn", @OA\Schema(type="integer"), description="Has opted in to
    receiving broadcast emails"),
     *             @OA\Property(property="flag_preventStaffAssign", @OA\Schema(type="integer"), description="Ineligible for staff
    role assignment"),
     *             @OA\Property(property="facility_join", @OA\Schema(type="string"), description="Date joined facility (YYYY-mm-dd
    hh:mm:ss)"),
     *             @OA\Property(property="promotion_eligible", @OA\Schema(type="boolean"), description="Is member eligible for
    promotion?"),
     *             @OA\Property(property="transfer_eligible", @OA\Schema(type="boolean"), description="Is member is eligible for
    transfer?"),
     *             @OA\Property(property="last_promotion", @OA\Schema(type="string"), description="Date last promoted"),
     *             @OA\Property(property="flag_homecontroller", @OA\Schema(type="boolean"), description="1-Belongs to VATUSA"),
     *             @OA\Property(property="lastactivity", @OA\Schema(type="string"), description="Date last seen on website"),
     *             @OA\Property(property="isMentor", @OA\Schema(type="boolean"), description="Has Mentor role"),
     *             @OA\Property(property="isSupIns", @OA\Schema(type="boolean"), description="Is a SUP and has INS role"),
     *             @OA\Property(property="membership", @OA\Schema(type="string"), description="'Home' or 'visit' depending on
    facility membership."),
     *             @OA\Property(property="roles", type="array",
     *                 @OA\Items(type="object",
     *                     @OA\Property(property="facility", @OA\Schema(type="string")),
     *                     @OA\Property(property="role", @OA\Schema(type="string"))
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
        $isFacStaff = Auth::check() && RoleHelper::isFacilityStaff(Auth::user()->cid, Auth::user()->facility);
        $isSeniorStaff = Auth::check() && RoleHelper::isSeniorStaff(Auth::user()->cid, Auth::user()->facility);

        $rosterArr = [];

        if ($membership == 'both') {
            $home = $facility->members;
            $visiting = $facility->visitors();
            $roster = $home->merge($visiting);
        } elseif ($membership == 'home') {
            $roster = $facility->members;
        } elseif ($membership == 'visit') {
            $roster = $facility->visitors();
        } else {
            return response()->api(generate_error("Malformed request"), 400);
        }

        $i = 0;
        foreach ($roster as $member) {
            $rosterArr[$i] = $member->toArray();
            $rosterArr[$i]['facility_join'] = Carbon::createFromFormat('Y-m-d H:i:s', $member->facility_join)
                ->format('c');
            $rosterArr[$i]['lastactivity'] = Carbon::createFromFormat('Y-m-d H:i:s', $member->lastactivity)->format
            ('c');
            if (!$hasAPIKey && !$isFacStaff) {
                $rosterArr[$i]['flag_broadcastOptedIn'] = null;
                $rosterArr[$i]['email'] = null;
            } else {
                //Override cache
                $rosterArr[$i]['flag_broadcastOptedIn'] = User::find($member->cid)->flag_broadcastOptedIn;
                $rosterArr[$i]['email'] = User::find($member->cid)->email;
            }
            if (!$isSeniorStaff) {
                //Senior Staff Only
                $rosterArr[$i]['flag_preventStaffAssign'] = null;
            } else {
                //Override cache
                $rosterArr[$i]['flag_preventStaffAssign'] = User::find($member->cid)->flag_preventStaffAssign;
            }


            //Add rating_short property
            $rosterArr[$i]['rating_short'] = RatingHelper::intToShort($member->rating);

            //Is Mentor
            $rosterArr[$i]['isMentor'] = $member->roles->where("facility", $facility->id)
                                                        ->where("role", "MTR")->count() > 0;

            //Has Ins Perms
            $rosterArr[$i]['isSupIns'] = $member->roles->where("facility", $facility->id)
                                                        ->where("role", "INS")->count() > 0;

            //Last promotion date
            $last_promotion = $member->lastPromotion();
            if ($last_promotion) {
                $rosterArr[$i]['last_promotion'] = $last_promotion->created_at;
            } else {
                $rosterArr[$i]['last_promotion'] = null;
            }

            //Membership
            if ($member->facility == $facility->id) {
                $rosterArr[$i]['membership'] = 'home';
            } else {
                $rosterArr[$i]['membership'] = 'visit';
                $rosterArr[$i]['facility_join'] = Visit::where('facility', $facility->id)
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
     * @OA\Post(
     *     path="/facility/{id}/roster/manageVisitor/{cid}",
     *     summary="Add member to visiting roster. [Key]",
     *     description="Add member to visiting roster.  API Key, JWT, or Session Cookie required (required role: ATM,
    DATM, WM, VATUSA STAFF)",
     * 
     * tags={"facility"},
     * security={"jwt"},
     * @OA\Parameter(name="id", in="query", description="Facility IATA ID", required=true, @OA\Schema(type="string")),
     * @OA\Parameter(name="cid", in="query", description="CID of controller", required=true, @OA\Schema
     * (type="integer")),
     * @OA\Response(
     *         response="401",
     *         description="Unauthorized",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     * @OA\Response(
     *         response="403",
     *         description="Forbidden -- needs to have role of ATM, DATM or VATUSA Division staff member",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     * @OA\Response(
     *         response="404",
     *         description="Not found or not active",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     * @OA\Response(
     *         response="422",
     *         description="User is already visiting this facility",
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

        $hasAPIKey = AuthHelper::validApiKeyv2($request->input('apikey', null), $id);
        $isSeniorStaff = Auth::check() && RoleHelper::isSeniorStaff(Auth::user()->cid, Auth::user()->facility,
                false);
        $isWM = Auth::check() && RoleHelper::has(Auth::user()->cid, $id, "WM");

        // Checks if requesting user is VATUSA or senior staff
        if (!$hasAPIKey && !$isSeniorStaff && !$isWM) {
            return response()->api(generate_error("Forbidden"), 403);
        }

        // Checks if user with specified cid exists
        $user = User::where('cid', $cid)->first();
        if (!$user) {
            return response()->api(
                generate_error("User not found"), 404
            );
        }

        // Checks if user is not ZAE
        if ($user->facility == "ZAE") {
            return response()->api(
                generate_error("User is in ZAE, cannot visit"), 422
            );
        }

        // Checks if user is a member at the specified facility
        if ($user->facility == $facility->id) {
            return response()->api(
                generate_error("User is a member at this facility"), 422
            );
        }

        // Checks if the visit already exists
        $visit = Visit::where('cid', $cid)->where('facility', $facility->id)->first();
        if ($visit) {
            return response()->api(
                generate_error("User is already visiting this facility"), 422
            );
        }

        if (!isTest()) {
            $visitor = new Visit();
            $visitor->cid = $user->cid;
            $visitor->facility = $facility->id;
            $visitor->save();

            if (Auth::check()) {
                log_action($user->cid, "User added to {$facility->id} visiting roster by " . Auth::user()->fullname()
                    . " (" . Auth::user()->cid . ")");
            } else {
                log_action($user->cid, "User added to {$facility->id} visiting roster");
            }

            Cache::forget("roster-$facility->id-visit");
            Cache::forget("roster-$facility->id-both");
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
     * @OA\Delete(
     *     path="/facility/{id}/roster/manageVisitor/{cid}",
     *     summary="Delete member from visiting roster. [Key]",
     *     description="Delete member from visiting roster.  API Key, JWT, or Session Cookie required (required role:
     *     ATM,
    DATM, VATUSA STAFF)",
     * 
     * tags={"facility"},
     * security={"jwt"},
     * @OA\Parameter(name="id", in="query", description="Facility IATA ID", required=true, @OA\Schema(type="string")),
     * @OA\Parameter(name="cid", in="query", description="CID of controller", required=true, @OA\Schema
     * (type="integer")),
     * @OA\RequestBody(
     * @OA\MediaType(
     *  mediaType="application/x-www-form-urlencoded",
     * @OA\Schema(
     * @OA\Parameter(name="reason", description="Reason for deletion", required=true, @OA\Schema(type="string")),
     * )
     * )
     * ),
     * @OA\Response(
     *         response="400",
     *         description="Malformed request, missing required parameter",
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
     *         description="Forbidden -- needs to have role of ATM, DATM or VATUSA Division staff member",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     * @OA\Response(
     *         response="404",
     *         description="Not found or not active",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     * @OA\Response(
     *         response="422",
     *         description="User is not visiting this facility",
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

        $hasAPIKey = AuthHelper::validApiKeyv2($request->input('apikey', null), $id);
        $isSeniorStaff = Auth::check() && RoleHelper::isSeniorStaff(Auth::user()->cid, Auth::user()->facility,
                false);
        $isWM = Auth::check() && RoleHelper::has(Auth::user()->cid, $id, "WM");

        // Checks if requesting user is VATUSA or senior staff
        if (!$hasAPIKey && !$isSeniorStaff && !$isWM) {
            return response()->api(generate_error("Forbidden"), 403);
        }

        // Checks if user with specified cid exists
        $user = User::where('cid', $cid)->first();
        if (!$user) {
            return response()->api(
                generate_error("User not found."), 404
            );
        }

        // Checks if request has a reason attached
        if (!$request->has("reason") || !$request->filled("reason")) {
            return response()->api(generate_error("Malformed request"), 400);
        }

        // Checks if user is a member at the specified facility
        $visit = Visit::where('cid', $cid)->where('facility', $facility->id)->first();
        if (!$visit) {
            return response()->api(
                generate_error("User is not visiting this facility"), 422
            );
        }

        if (!isTest()) {
            $facname = $facility->name;

            EmailHelper::sendEmail(
                [$user->email, "$facility-atm@vatusa.net", "$facility-datm@vatusa.net", "vatusa2@vatusa.net"],
                "Removal from $facname Visiting Roster",
                "emails.user.removedVisit",
                [
                    'name' => $user->fname . " " . $user->lname,
                    'cid' => $user->cid,
                    'facility' => $facility,
                    'reason' => $request->input("reason"),
                ]
            );
            $visit->delete();

            if (Auth::check()) {
                log_action($user->cid,
                    "User removed from {$facility->id} visiting roster by " . Auth::user()->fullname()
                    . ": " . $request->input("reason"));
            } else {
                log_action($user->cid,
                    "User removed from {$facility->id} visiting roster: " . $request->input("reason"));
            }


            Cache::forget("roster-$facility->id-visit");
            Cache::forget("roster-$facility->id-both");
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
     * @OA\Delete(
     *     path="/facility/{id}/roster/{cid}",
     *     summary="Delete member from facility roster. [Key]",
     *     description="Delete member from facility roster.  API Key, JWT, or Session Cookie required (required role:
     *     ATM,
    DATM, VATUSA STAFF)",
     * 
     * tags={"facility"},
     * security={"jwt"},
     * @OA\Parameter(name="id", in="query", description="Facility IATA ID", required=true, @OA\Schema(type="string")),
     * @OA\Parameter(name="cid", in="query", description="CID of controller", required=true, @OA\Schema
     * (type="integer")),
     * @OA\RequestBody(
     * @OA\MediaType(
     * mediaType="application/x-www-form-urlencoded",
     * @OA\Schema(
     * @OA\Parameter(name="reason", description="Reason for deletion", required=true, @OA\Schema(type="string")),
     * @OA\Parameter(name="by", description="Staff member responsible for deletion - only required with
     *                           API Key", required=false, @OA\Schema(type="integer")),
     * )
     * )
     * ),
     * @OA\Response(
     *         response="400",
     *         description="Malformed request, missing required parameter",
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
     *         description="Forbidden -- needs to have role of ATM, DATM or VATUSA Division staff member",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     * @OA\Response(
     *         response="404",
     *         description="Not found or not active",
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

        if (!AuthHelper::validApiKeyv2($id) && !RoleHelper::isVATUSAStaff() && (Auth::check() && !RoleHelper::isSeniorStaff(Auth::user()->cid,
                    $id, false))) {
            return response()->api(generate_error("Forbidden"), 403);
        }

        if (!Auth::check() && !$request->has('by')) {
            return response()->api(
                generate_error("Missing staff CID (by)"), 400);
        } else {
            if ($request->has('by') && (!User::find($request->by) || !RoleHelper::isSeniorStaff($request->by, $facility->id, false))) {
                return response()->api(
                    generate_error("Invalid staff CID"), 400);
            }
        }
        $by = Auth::check() ? Auth::user()->cid : $request->by;

        $user = User::find($cid);
        if (!$user || $user->facility != $facility->id) {
            return response()->api(
                generate_error("User not found or not in facility"), 404
            );
        }

        if (!$request->has("reason") || !$request->filled("reason")) {
            return response()->api(generate_error("Malformed request, missing reason"), 400);
        }

        if (!isTest()) {
            $user->removeFromFacility(
                $by, $request->input("reason")
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
     * @OA\Get(
     *     path="/facility/{id}/transfers",
     *     summary="Get pending transfers. [Key]",
     *     description="Get pending transfers. Requires API Key, Session Cookie, or JWT",
     *     tags={"facility"},
     *     security={"jwt","apikey"},
     *     @OA\Parameter(name="id", in="query", description="Facility IATA ID", required=true, @OA\Schema(type="string")),
     *     @OA\Response(
     *         response="400",
     *         description="Malformed request, missing required parameter",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Forbidden -- needs to be a staff member, other than mentor",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Not found or not active",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="OK",
     *         @OA\Schema(
     *             type="object",
     *             @OA\Property(property="status", @OA\Schema(type="string")),
     *             @OA\Property(property="transfers", type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", @OA\Schema(type="integer"), description="Transfer ID"),
     *                     @OA\Property(property="cid", @OA\Schema(type="integer"), description="VATSIM ID"),
     *                     @OA\Property(property="fname", @OA\Schema(type="string"), description="First name"),
     *                     @OA\Property(property="lname", @OA\Schema(type="string"), description="Last name"),
     *                     @OA\Property(property="email", @OA\Schema(type="string"), description="Email, if authenticated as staff
    member and/or api key is present."),
     *                     @OA\Property(property="reason", @OA\Schema(type="string"), description="Transfer reason; must be
     *                                                      authenticated as senior staff."),
     *                     @OA\Property(property="fromFac", type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", @OA\Schema(type="integer"), description="Facility ID (ex. ZSE)"),
     *                             @OA\Property(property="name", @OA\Schema(type="integer"), description="Facility Name (ex.
    Seattle ARTCC)")
     *                         )
     *                     ),
     *                     @OA\Property(property="rating", @OA\Schema(type="string"), description="Short string rating (S1, S2)"),
     *                     @OA\Property(property="intRating", @OA\Schema(type="integer"), description="Numeric rating (OBS = 1,
    etc)"),
     *                     @OA\Property(property="date", @OA\Schema(type="string"), description="Date transfer submitted
    (YYYY-MM-DD)"),
     *                 ),
     *             ),
     *         ),
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

        if (!AuthHelper::validApiKeyv2($request->input('apikey', null)) && !Auth::check()) {
            return response()->api(generate_error("Unauthorized"), 401);
        }

        if (!AuthHelper::validApiKeyv2($request->input('apikey', null))
            && !RoleHelper::isFacilityStaff(Auth::user()->cid, $id)
            && !RoleHelper::isVATUSAStaff(Auth::user()->cid)
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
                        null)) || (Auth::check() && RoleHelper::isFacilityStaff(Auth::user()->cid)))
                    ? $transfer->user->email : null,
                'reason'    => (Auth::check() && RoleHelper::isSeniorStaff(Auth::user()->cid)) ? $transfer->reason : null,
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
     * @OA\Put(
     *     path="/facility/{id}/transfers/{transferId}",
     *     summary="Modify transfer request.  [Key]",
     *     description="Modify transfer request. Requires API Key, Session Cookie, or JWT (required role: ATM, DATM,
    VATUSA STAFF)",  tags={"facility"}, security={"jwt"},
     * @OA\Parameter(name="id", in="query", description="Facility IATA ID", required=true, @OA\Schema(type="string")),
     * @OA\Parameter(name="transferId", in="query", description="Transfer ID", @OA\Schema(type="integer"),
     *     required=true),
     * @OA\RequestBody(
     * @OA\MediaType(
     * mediaType="application/x-www-form-urlencoded",
     * @OA\Schema(
     * @OA\Parameter(name="action", @OA\Schema(type="string"), required=true,
     *                                   description="Action to take on transfer request. Valid values:
    accept,reject"),
     * @OA\Parameter(name="reason", @OA\Schema(type="string"), description="Reason for transfer request rejection
    [required for rejections]"),
     * @OA\Parameter(name="by", @OA\Schema(type="integer"), description="Staff member responsible for
     * trasnfer [required for API Key]"),
     * )
     * )
     * ),
     * @OA\Response(
     *         response="400",
     *         description="Malformed request, missing required parameter",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     * @OA\Response(
     *         response="403",
     *         description="Forbidden -- needs to be a staff member, other than mentor",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     * @OA\Response(
     *         response="404",
     *         description="Not found or not active",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     * @OA\Response(
     *         response="410",
     *         description="Gone",
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

        if (!Auth::check() && !$request->has('by')) {
            return response()->api(
                generate_error("Missing staff CID (by)"), 400);
        } else {
            if ($request->has('by') && (!User::find($request->by) || !RoleHelper::isSeniorStaff($request->by, $facility->id, false))) {
                return response()->api(
                    generate_error("Invalid staff CID"), 400);
            }
        }
        $by = Auth::check() ? Auth::user()->cid : $request->by;

        if (!AuthHelper::validApiKeyv2($request->input('apikey', null)) && !Auth::check()) {
            return response()->api(generate_error("Unauthorized"), 401);
        }

        if (!RoleHelper::isSeniorStaff($by, $id)) {
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
                $transfer->accept($by);
            } else {
                $transfer->reject($by, $request->input("reason"));
            }
        }

        return response()->ok();
    }

    private function hasValidOAuthPerms($facilityId)
    {
        if (
            !RoleHelper::isSeniorStaff(Auth::user()->cid, $facilityId, false)
            && !RoleHelper::has(Auth::user()->cid, $facilityId, "WM")
            && !RoleHelper::isVATUSAStaff(Auth::user()->cid)
        ) {
            return false;
        }

        return true;
    }

    public function isRedirectValid($redirect)
    {
        if (!is_array($redirect)) {
            return false;
        } else {
            foreach ($redirect as $value) {
                if (gettype($value) != "string") {
                    return false;
                }
            }
        }

        return true;
    }
}
