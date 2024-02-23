<?php

namespace App\Http\Controllers\API\v2;

use App\Helpers\AuthHelper;
use App\Helpers\RoleHelper;
use App\Helpers\Helper;
use App\TMUFacility;
use App\TMUNotice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Illuminate\Http\Request;
use Mews\Purifier\Facades\Purifier;

/**
 * Class TMUController
 * @package App\Http\Controllers\API\v2
 */
class TMUController extends APIController
{

    /**
     * @OA\Get(
     *     path="/tmu/notices/(tmufacid?)",
     *     summary="Get list of TMU Notices.",
     *     description="Get list of TMU Notices for either all of VATUSA or for the specified TMU Map ID.",
     *     tags={"tmu"},
     *     @OA\Parameter(name="facility", in="path", @OA\Schema(type="string"), description="TMU Facility/Map ID (optional)",
     *                                     required=false),
     *     @OA\Parameter(name="children", in="query", @OA\Schema(type="boolean"), description="If a parent map is
     *     selected,
    include its children TMU's Notices.", required=false),
     *     @OA\Parameter(name="onlyactive", in="query", @OA\Schema(type="boolean"), description="Only include active
     *     notices.
    Default = true.", required=false),
     *     @OA\Response(
     *         response="200",
     *         description="OK",
     *         @OA\Schema(
     *             type="array",
     *             @OA\Items(type="object",
     *                 @OA\Property(property="id",type="integer",description="TMU Notice ID"),
     *                 @OA\Property(property="tmu_facility",type="array",
     *                               @OA\Items(type="object",
     *                                          @OA\Property(property="id", @OA\Schema(type="string"), description="TMU Facility ID"),
     *                                          @OA\Property(property="name", @OA\Schema(type="string"), description="TMU Facility Name"),
     *                                          @OA\Property(property="parent", @OA\Schema(type="string"), description="Parent TMU Facility/ARTCC")
     *                               )
     *                 ),
     *                 @OA\Property(property="priority",type="string",description="Priority of notice
    (0:Low,1:Standard,2:Urgent)"),
     *                 @OA\Property(property="message",type="string",description="Notice content"),
     *                 @OA\Property(property="expire_date", @OA\Schema(type="string"), description="Expiration time in Zulu
    (YYYY-MM-DD H:i:s)"),
     *                 @OA\Property(property="start_date", @OA\Schema(type="string"), description="Start time in Zulu (YYYY-MM-DD
    H:i:s)"),
     *                 @OA\Property(property="is_delay", @OA\Schema(type="boolean"), description="TMU Notice is a ground stop or delay"),
     *                 @OA\Property(property="is_pref_route", @OA\Schema(type="boolean"), description="TMU Notice is a preferred routing")
     *                   )
     *                )
     *             ),
     *         ),
     *     )
     * ),
     * @param \Illuminate\Http\Request $request
     *
     * @param \App\TMUFacility         $facility
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getNotices(Request $request, TMUFacility $facility = null)
    {
        $onlyActive = $request->input('onlyactive', true);
        if ($facility) {
            if ($request->input('children', null) == false) {
                $notices = $facility->tmuNotices();
                if ($onlyActive === true) {
                    $notices = $notices->active();
                }
                $notices = $notices->with('tmuFacility:id,name,parent')->get()->toArray();
            } else {
                $notices = TMUNotice::with('tmuFacility:id,name,parent');
                if ($onlyActive === true) {
                    $notices = $notices->active();
                }
                $notices = $notices->orderBy('priority', 'DESC')->orderBy('tmu_facility_id')->orderBy('start_date',
                    'DESC');

                $allFacs = TMUFacility::where('id', $facility->id)->orWhere('parent', $facility->id);
                $notices = $notices->whereIn('tmu_facility_id', $allFacs->get()->pluck('id'))
                    ->get()->toArray();
            }
        } else {
            $notices = TMUNotice::with('tmuFacility:id,name,parent');
            $notices = $onlyActive ? $notices->active()->get()->toArray() : $notices->get()->toArray();
        }

        return response()->api($notices);
    }

    /**
     * @OA\Get(
     *     path="/tmu/notice/{id}",
     *     summary="Get TMU Notice info.",
     *     description="Get information for a specific TMU Notice.",
     *     tags={"tmu"},
     *     @OA\Parameter(name="id", in="path", @OA\Schema(type="string"), description="TMU Notice ID",
     *                                     required=true),
     *     @OA\Response(
     *         response="200",
     *         description="OK",
     *         @OA\Schema(
     *             type="array",
     *             @OA\Items(type="object",
     *                 @OA\Property(property="id",type="integer",description="TMU Notice ID"),
     *                 @OA\Property(property="tmu_facility",type="array",
     *                                @OA\Items(type="object",
     *                                          @OA\Property(property="id", @OA\Schema(type="string"), description="TMU Facility ID"),
     *                                          @OA\Property(property="name", @OA\Schema(type="string"), description="TMU Facility Name"),
     *                                          @OA\Property(property="parent", @OA\Schema(type="string"), description="Parent TMU Facility/ARTCC")
     *                               ),
     *                 ),
     *                 @OA\Property(property="priority",type="string",description="Priority of notice
                                                                      (0:Low,1:Standard,2:Urgent)"),
     *                 @OA\Property(property="message",type="string",description="Notice content"),
     *                 @OA\Property(property="expire_date", @OA\Schema(type="string"), description="Expiration time in Zulu (YYYY-MM-DD H:i:s)"),
     *                 @OA\Property(property="start_date", @OA\Schema(type="string"), description="Start time in Zulu (YYYY-MM-DD H:i:s)"),
     *                 @OA\Property(property="is_delay", @OA\Schema(type="boolean"), description="TMU Notice is a ground stop or delay."),
     *                 @OA\Property(property="is_pref_route", @OA\Schema(type="boolean"), description="TMU Notice is a preferred routing")
     *                       )
     *                )
     *             ),
     *         ),
     *     )
     * ),
     * @param \Illuminate\Http\Request $request
     *
     * @param \App\TMUNotice           $notice
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getNotice(Request $request, TMUNotice $notice)
    {
        return response()->api(array_merge($notice->toArray(),
            ["tmu_facility" => ["id"     => $notice->tmuFacility->id,
                                "name"   => $notice->tmuFacility->name,
                                "parent" => $notice->tmuFacility->parent
            ]
            ]));
    }

    /**
     * @OA\Post(
     *     path="/tmu/notices",
     *     summary="Add new TMU Notice. [Key]",
     *     description="Add new TMU Notice. Requires API Key, JWT, or Session Cookie (required roles:
    [N/A for API Key] ATM, DATM, TA, EC, INS)",  tags={"tmu"},
     *     security={"apikey","jwt","session"},
     *      tags={"tmu"},
     * @OA\RequestBody(@OA\MediaType(mediaType="application/x-www-form-urlencoded",@OA\Schema(
     * @OA\Parameter(name="facility",@OA\Schema(type="string"),description="TMU Facility/Map ID",required=true),
     * @OA\Parameter(name="priority",@OA\Schema(type="string"),description="Priority of notice
    (1: Low, 2: Standard, 3: Urgent)",required=true),
     * @OA\Parameter(name="message",@OA\Schema(type="string"),description="Notice content",required=true),
     * @OA\Parameter(name="start_date",@OA\Schema(type="string"),description="Effective date (YYYY-MM-DD
    HH:MM)"),
     * @OA\Parameter(name="expire_date",@OA\Schema(type="string"),description="Expiration date (YYYY-MM-DD
    HH:MM)"),
     * @OA\Parameter(name="is_delay",@OA\Schema(type="boolean"),description="TMU Notice is a ground stop or delay"),
     * @OA\Parameter(name="is_pref_route",@OA\Schema(type="boolean"),description="TMU Notice is a preferred routing"),
     * ))),
     * @OA\Response(
     *         response="400",
     *         description="Malformed request",
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
     *         ),
     *         
     *     )
     * ),
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function addNotice(Request $request)
    {
        $facility = strtoupper($request->input('facility', null)); ///TMU Map Facility ID
        $startdate = urldecode($request->input('start_date', null));
        $expdate = urldecode($request->input('expire_date', null));
        $priority = $request->input('priority', 1); //Default: standard priority
        $message = Purifier::clean(nl2br($request->input('message', null)), config_path('purifier-ntos'));
        $isDelay = $request->input('is_delay', null);
        $isDelay = $isDelay === true || $isDelay === "on";
        $isPrefRoute = $request->input('is_pref_route', null);
        $isPrefRoute = $isPrefRoute === true || $isPrefRoute === "on";

        if (!$facility || !$message) {
            return response()->api(generate_error("Malformed request, missing fields"), 400);
        }

        $tmuFac = TMUFacility::find($facility);
        if (!$tmuFac) {
            return response()->api(generate_error("TMU facility does not exist"), 404);
        }

        $fac = $tmuFac->parent ? $tmuFac->parent : $tmuFac->id; //ZXX
        if (Auth::check()) {
            if (!RoleHelper::isVATUSAStaff() && Auth::user()->facility != $fac) {
                return response()->api(generate_error("Forbidden. Cannot add notice for another ARTCC's TMU."), 403);
            }
            if (!(RoleHelper::isFacilityStaff() ||
                RoleHelper::isInstructor() || RoleHelper::isMentor())) {
                return response()->api(generate_error("Forbidden."), 403);
            }
        } else {
            if (!AuthHelper::validApiKeyv2($request->input('apikey', null), $fac)) {
                return response()->api(generate_error("Forbidden. Cannot add notice for another ARTCC's TMU."), 403);
            }
        }

        if ($startdate) {
            try {
                $cStartDate = Carbon::createFromFormat('Y-m-d H:i', $startdate);
            } catch (InvalidArgumentException $e) {
                return response()->api(generate_error("Malformed request, invalid start date format (Y-m-d H:i)."),
                    400);
            }
        } else {
            $cStartDate = Carbon::now('utc');
        }
        $startdate = $cStartDate->format('Y-m-d H:i:s');

        if ($expdate) {
            try {
                $cExpDate = Carbon::createFromFormat('Y-m-d H:i', $expdate);
                $expdate = $cExpDate->format('Y-m-d H:i:s');
            } catch (InvalidArgumentException $e) {
                return response()->api(generate_error("Malformed request, invalid expire date format (Y-m-d H:i)"),
                    400);
            }

            if ($cExpDate->isPast()) {
                return response()->api(generate_error("Malformed request, expire date cannot be in the past."), 400);
            }
            if ($cExpDate->eq($cStartDate)) {
                return response()->api(generate_error("Malformed request, expire date cannot be the same as start date."),
                    400);
            }
            if ($cExpDate->lt($cStartDate)) {
                return response()->api(generate_error("Malformed request, expire date cannot be before start date."),
                    400);
            }
        } else {
            $expdate = null;
        }

        if (!in_array(intval($priority), [1, 2, 3])) {
            return response()->api(generate_error("Malformed request, priority must be 1, 2, or 3"), 400);
        }

        if (!isTest()) {
            $notice = new TMUNotice;
            $notice->message = $message;
            $notice->priority = $priority;
            $notice->start_date = $startdate;
            $notice->expire_date = $expdate;
            $notice->is_delay = $isDelay;
            $notice->is_pref_route = $isPrefRoute;
            $tmuFac->tmuNotices()->save($notice);
        }

        return response()->ok(['id' => isTest() ? 0 : $notice->id]);
    }

    /**
     * @OA\Put(
     *     path="/tmu/notice/(id)",
     *     summary="Edit TMU Notice. [Key]",
     *     description="Edit TMU Notice. Requires API Key, JWT, or Session Cookie (required roles:
    [N/A for API Key] ATM, DATM, TA, EC, INS)",  tags={"tmu"},
     *     security={"apikey","jwt","session"},
     *      tags={"tmu"},
     * @OA\Parameter(name="id",@OA\Schema(type="integer"),description="TMU Notice ID",in="path",required=true),
     * @OA\RequestBody(@OA\MediaType(mediaType="application/x-www-form-urlencoded",@OA\Schema(
     * @OA\Parameter(name="facility",@OA\Schema(type="string"),description="TMU Facility/Map ID"),
     * @OA\Parameter(name="priority",@OA\Schema(type="string"),description="Priority of notice
    (1: Low, 2: Standard, 3: Urgent)"),
     * @OA\Parameter(name="message",@OA\Schema(type="string"),description="Notice content"),
     * @OA\Parameter(name="start_date",@OA\Schema(type="string"),description="Start time (YYYY-MM-DD HH:MM)"),
     * @OA\Parameter(name="expire_date",@OA\Schema(type="string"),description="Expiration time (YYYY-MM-DD HH:MM) - null for no
    expiration"),
     * @OA\Parameter(name="is_delay",@OA\Schema(type="boolean"),description="TMU Notice is a ground stop or delay."),
     * @OA\Parameter(name="is_pref_route",@OA\Schema(type="boolean"),description="TMU Notice is a preferred routing"),
     * ))),
     * @OA\Response(
     *         response="400",
     *         description="Malformed request",
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
     *         ),
     *         
     *     )
     * )
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\TMUNotice $notice
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function editNotice(Request $request, TMUNotice $notice)
    {
        $facility = strtoupper($request->input('facility', $notice->tmuFacility->id)); ///TMU Map Facility ID
        if (!$facility) {
            //Fallback in case HTML form is invalid
            $facility = $notice->tmuFacility->id;
        }

        $startdate = $request->input('start_date', $notice->start_date);
        if (!$startdate) {
            $startdate = $notice->start_date;
        }
        if (!($startdate instanceof Carbon)) {
            $startdate = urldecode($startdate);
        }

        $expdate = $request->input('expire_date', $notice->expire_date);
        if (!$expdate) {
            $expdate = $notice->expire_date;
        }
        if (!($expdate instanceof Carbon)) {
            $expdate = urldecode($expdate);
        }

        $priority = $request->input('priority', $notice->priority); //Default: standard priority
        if (!$priority) {
            $priority = $notice->priority;
        }

        $message = Purifier::clean(nl2br($request->input('message', null)), config_path('purifier-ntos'));
        if (!$message) {
            $message = $notice->message;
        }

        $isDelay = $request->input('is_delay', null);
        $isPrefRoute = $request->input('is_pref_route', null);
        if(!$request->ajax()) {
            //API Call, set defaults
            if (is_null($isDelay)) {
                $isDelay = $notice->is_delay;
            }
            if (is_null($isPrefRoute)) {
                $isPrefRoute = $notice->is_pref_route;
            }
        }

        $tmuFac = TMUFacility::find($facility);
        if (!$tmuFac) {
            return response()->api(generate_error("TMU facility does not exist"), 404);
        }

        $fac = $tmuFac->parent ? $tmuFac->parent : $tmuFac->id; //ZXX
        if (Auth::check()) {
            if (!RoleHelper::isVATUSAStaff() && Auth::user()->facility != $fac) {
                return response()->api(generate_error("Forbidden. Cannot edit notice for another ARTCC's TMU."), 403);
            }
            if (!(RoleHelper::isFacilityStaff() ||
                RoleHelper::isInstructor() || RoleHelper::isMentor())) {
                return response()->api(generate_error("Forbidden."), 403);
            }
        } else {
            if (!AuthHelper::validApiKeyv2($request->input('apikey', null), $fac)) {
                return response()->api(generate_error("Forbidden. Cannot edit notice for another ARTCC's TMU."), 403);
            }
        }

        //Start date is always present
        if (!($startdate instanceof Carbon)) {
            //not from DB
            try {
                $cStartDate = Carbon::createFromFormat('Y-m-d H:i', $startdate);
            } catch (InvalidArgumentException $e) {
                return response()->api(generate_error("Malformed request, invalid start date format (Y-m-d H:i)."),
                    400);
            }
        } else {
            $cStartDate = $startdate;
        }

        if ($expdate) {
            if (!($expdate instanceof Carbon)) {
                //not from DB
                try {
                    $cExpDate = Carbon::createFromFormat('Y-m-d H:i', $expdate);
                } catch (InvalidArgumentException $e) {
                    return response()->api(generate_error("Malformed request, invalid expire date format (Y-m-d H:i)"),
                        400);
                }
            } else {
                $cExpDate = $expdate;
            }

            if ($cExpDate->isPast()) {
                return response()->api(generate_error("Malformed request, expire date cannot be in the past."), 400);
            }
            if ($cExpDate->eq($cStartDate)) {
                return response()->api(generate_error("Malformed request, expire date cannot be the same as start date."),
                    400);
            }
            if ($cExpDate->lt($cStartDate)) {
                return response()->api(generate_error("Malformed request, expire date cannot be before start date."),
                    400);
            }
        } else {
            $expdate = $cExpDate = null;
        }

        if (!in_array(intval($priority), [1, 2, 3])) {
            return response()->api(generate_error("Malformed request, priority must be 1, 2, or 3"), 400);
        }

        if (!isTest()) {
            $notice->message = $message;
            $notice->priority = $priority;
            $notice->start_date = $cStartDate;
            $notice->expire_date = $cExpDate;
            $notice->is_delay = $isDelay === true || $isDelay === "on";
            $notice->is_pref_route = $isPrefRoute === true || $isPrefRoute === "on";
            $tmuFac->tmuNotices()->save($notice);
        }

        return response()->ok();
    }

    /**
     * @OA\Delete(
     *     path="/tmu/notice/(id)",
     *     summary="Delete TMU Notice. [Key]",
     *     description="Delete solo certification. Requires API Key, JWT, or Session cookie (required roles: [N/A
    for API Key] ATM, DATM, TA, EC, INS)",
     *      tags={"tmu"},
     *     security={"apikey","jwt","session"},
     * @OA\Parameter(name="id", in="path", @OA\Schema(type="integer"), required=true, description="TMU Notice ID"),
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
     * @param \App\TMUNotice           $notice
     *
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public
    function removeNotice(
        Request $request,
        TMUNotice $notice
    ) {
        $fac = $notice->tmuFacility->parent ? $notice->tmuFacility->parent : $notice->tmuFacility->id; //ZXX
        if (Auth::check()) {
            if (!RoleHelper::isVATUSAStaff() && Auth::user()->facility != $fac) {
                return response()->api(generate_error("Forbidden. Cannot delete another ARTCC's TMU notice."), 403);
            }
            if (!(RoleHelper::isFacilityStaff() ||
                RoleHelper::isInstructor() || RoleHelper::isMentor())) {
                return response()->api(generate_error("Forbidden."), 403);
            }
        } else {
            if (!AuthHelper::validApiKeyv2($request->input('apikey', null), $fac)) {
                return response()->api(generate_error("Forbidden. Cannot edit another ARTCC's TMU notice."), 403);
            }
        }

        if (!isTest()) {
            $notice->delete();
        }

        return response()->ok();
    }
}
