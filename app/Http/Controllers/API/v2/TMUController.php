<?php

namespace App\Http\Controllers\API\v2;

use App\Helpers\AuthHelper;
use App\Helpers\RoleHelper;
use App\Role;
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
     * @SWG\Get(
     *     path="/tmu/notices/(tmufacid?)",
     *     summary="Get list of TMU Notices.",
     *     description="Get list of TMU Notices for either all of VATUSA or for the specified TMU Map ID.",
     *     produces={"application/json"},
     *     tags={"tmu"},
     *     @SWG\Parameter(name="facility", in="path", type="string", description="TMU Facility/Map ID (optional)",
     *                                     required=false),
     *     @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(
     *             type="array",
     *             @SWG\Items(type="object",
     *                 @SWG\Property(property="id",type="integer",description="TMU Notice ID"),
     *                 @SWG\Property(property="tmu_facility_id",type="string",description="TMU Map ID"),
     *                 @SWG\Property(property="priority",type="string",description="Priority of notice
     *                                                                               (0:Low,1:Standard,2:Urgent)"),
     *                 @SWG\Property(property="message",type="string",description="Notice content"),
     *                 @SWG\Property(property="expire_date", type="string", description="Expiration time in Zulu
     *                                                       (YYYY-MM-DD
    H:i:s)"),
     *                 @SWG\Property(property="start_date", type="string", description="Start time in Zulu (YYYY-MM-DD
    H:i:s)")
     *                   )
     *                )
     *             ),
     *         ),
     *     )
     * ),
     * @param \Illuminate\Http\Request $request
     *
     * @param \App\TMUFacility|null    $facility
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getNotices(Request $request, TMUFacility $facility = null)
    {
        if ($facility) {
            $notices = $facility->tmuNotices()->get()->toArray();
        } else {
            $notices = TMUNotice::all()->toArray();
        }

        return response()->api($notices);
    }

    /**
     * @SWG\Get(
     *     path="/tmu/notice/{id}",
     *     summary="Get TMU Notice info.",
     *     description="Get information for a specific TMU Notice.",
     *     produces={"application/json"},
     *     tags={"tmu"},
     *     @SWG\Parameter(name="id", in="path", type="string", description="TMU Notice ID",
     *                                     required=true),
     *     @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(
     *             type="array",
     *             @SWG\Items(type="object",
     *                 @SWG\Property(property="id",type="integer",description="TMU Notice ID"),
     *                 @SWG\Property(property="tmu_facility_id",type="string",description="TMU Map ID"),
     *                 @SWG\Property(property="priority",type="string",description="Priority of notice
     *                                                                               (0:Low,1:Standard,2:Urgent)"),
     *                 @SWG\Property(property="message",type="string",description="Notice content"),
     *                 @SWG\Property(property="expire_date", type="string", description="Expiration time in Zulu
     *                                                       (YYYY-MM-DD
    H:i:s)"),
     *                 @SWG\Property(property="start_date", type="string", description="Start time in Zulu (YYYY-MM-DD
    H:i:s)")
     *                   )
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
        return response()->api($notice->toArray());
    }

    /**
     * @SWG\Post(
     *     path="/tmu/notices",
     *     summary="Add new TMU Notice. [Key]",
     *     description="Add new TMU Notice. Requires API Key, JWT, or Session Cookie (required roles:
    [N/A for API Key] ATM, DATM, TA, EC, INS)", produces={"application/json"}, tags={"tmu"},
     *     security={"apikey","jwt","session"},
     *     produces={"application/json"}, tags={"tmu"},
     * @SWG\Parameter(name="facility",type="string",description="TMU Facility/Map ID",in="formData",required=true),
     * @SWG\Parameter(name="priority",type="string",description="Priority of notice
    (1: Low, 2: Standard, 3: Urgent)",in="formData",required=true),
     * @SWG\Parameter(name="message",type="string",description="Notice content",in="formData",required=true),
     * @SWG\Parameter(name="start_date",type="string",description="Effective date (YYYY-MM-DD
    HH:MM)",in="formData"),
     * @SWG\Parameter(name="expire_date",type="string",description="Expiration date (YYYY-MM-DD
    HH:MM)",in="formData"),
     * @SWG\Response(
     *         response="400",
     *         description="Malformed request",
     *         @SWG\Schema(ref="#/definitions/error"),
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
     *         ),
     *         examples={"application/json":{"status"="OK"}}
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

        if (!$facility || !$message) {
            return response()->api(generate_error("Malformed request, missing fields"), 400);
        }

        $tmuFac = TMUFacility::find($facility);
        if (!$tmuFac->exists()) {
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
            $tmuFac->tmuNotices()->save($notice);
        }

        return response()->ok();
    }

    /**
     * @SWG\Put(
     *     path="/tmu/notice/(id)",
     *     summary="Edit TMU Notice. [Key]",
     *     description="Edit TMU Notice. Requires API Key, JWT, or Session Cookie (required roles:
    [N/A for API Key] ATM, DATM, TA, EC, INS)", produces={"application/json"}, tags={"tmu"},
     *     security={"apikey","jwt","session"},
     *     produces={"application/json"}, tags={"tmu"},
     * @SWG\Parameter(name="id",type="integer",description="TMU Notice ID",in="path",required=true),
     * @SWG\Parameter(name="facility",type="string",description="TMU Facility/Map ID",in="formData"),
     * @SWG\Parameter(name="priority",type="string",description="Priority of notice
    (1: Low, 2: Standard, 3: Urgent)",in="formData"),
     * @SWG\Parameter(name="message",type="string",description="Notice content",in="formData"),
     * @SWG\Parameter(name="start_date",type="string",description="Start time (YYYY-MM-DD HH:MM)", in="formData"),
     * @SWG\Parameter(name="expire_date",type="string",description="Expiration time (YYYY-MM-DD HH:MM) - null for no
    expiration",in="formData"),
     * @SWG\Response(
     *         response="400",
     *         description="Malformed request",
     *         @SWG\Schema(ref="#/definitions/error"),
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
     *         ),
     *         examples={"application/json":{"status"="OK"}}
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

        $tmuFac = TMUFacility::find($facility);
        if (!$tmuFac->exists()) {
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
            $tmuFac->tmuNotices()->save($notice);
        }

        return response()->ok();
    }

    /**
     * @SWG\Delete(
     *     path="/tmu/notice/(id)",
     *     summary="Delete TMU Notice. [Key]",
     *     description="Delete solo certification. Requires API Key, JWT, or Session cookie (required roles: [N/A
    for API Key] ATM, DATM, TA, EC, INS)",
     *     produces={"application/json"}, tags={"tmu"},
     *     security={"apikey","jwt","session"},
     * @SWG\Parameter(name="id", in="path", type="integer", required=true, description="TMU Notice ID"),
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