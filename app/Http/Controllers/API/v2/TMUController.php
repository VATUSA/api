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

/**
 * Class TMUController
 * @package App\Http\Controllers\API\v2
 */
class TMUController extends APIController
{
    /**
     * @SWG\Post(
     *     path="/tmu/notices",
     *     summary="Add new TMU Notice. [Key]",
     *     description="Add new TMU Notice. Requires API Key, JWT, or Session Cookie (required roles:
    [N/A for API Key] ATM, DATM, TA, EC, INS)", produces={"application/json"}, tags={"solo"},
     *     security={"apikey","jwt","session"},
     *     produces={"application/json"}, tags={"tmu"},
     * @SWG\Parameter(name="tmu_facility_id",type="string",description="TMU Map ID",in="formData",required=true),
     * @SWG\Parameter(name="priority",type="string",description="Priority of notice
    (0: Low, 1: Standard, 2: Urgent)",in="formData",required=true),
     * @SWG\Parameter(name="message",type="string",description="Notice content",in="formData",required=true),
     * @SWG\Parameter(name="start_date",type="string",description="Effective date (YYYY-MM-DD
     *                                                                         H:i:s)",in="formData"),
     * @SWG\Parameter(name="expire_date",type="string",description="Expiration date (YYYY-MM-DD
     *                                                                         H:i:s)",in="formData"),
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
        $facility = $request->input('facility', null); ///TMU Map Facility ID
        $startdate = $request->input('start_date', null);
        $expdate = $request->input('expire_date', null);
        $priority = $request->input('priority', 1); //Default: standard priority
        $message = $request->input('message', null);


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
                RoleHelper::isVATUSAStaff() ||
                RoleHelper::isInstructor())) {
                return response()->api(generate_error("Forbidden."), 403);
            }
        } else {
            if (!AuthHelper::validApiKeyv2($request->input('apikey', null), $fac)) {
                return response()->api(generate_error("Forbidden. Cannot add notice for another ARTCC's TMU."), 403);
            }
        }

        if ($startdate) {
            try {
                $cStartDate = Carbon::createFromFormat('Y-m-d H:i:s', $startdate);
            } catch (InvalidArgumentException $e) {
                return response()->api(generate_error("Malformed request, invalid start date format (Y-m-d H:i:s)."),
                    400);
            }
            if ($cStartDate->isPast()) {
                return response()->api(generate_error("Malformed request, start date cannot be in the past."), 400);
            }
        } else {
            $cStartDate = Carbon::now();
            $startdate = $cStartDate->format('Y-m-d H:i:s');
        }

        if ($expdate) {
            try {
                $cExpDate = Carbon::createFromFormat('Y-m-d H:i:s', $expdate);
            } catch (InvalidArgumentException $e) {
                return response()->api(generate_error("Malformed request, invalid expire date format (Y-m-d H:i:s)"),
                    400);
            }

            if ($cExpDate->isPast()) {
                return response()->api(generate_error("Malformed request, expire date cannot be in the past."), 400);
            }
            if ($cExpDate->eq($cStartDate)) {
                return response()->api(generate_error("Malformed request, expire date cannot be the same as start date."),
                    400);
            }
            if ($cExpDate < $cStartDate) {
                return response()->api(generate_error("Malformed request, expire date cannot be before start date."),
                    400);
            }
        }

        if (!in_array(intval($priority), [1, 2, 3])) {
            return response()->api(generate_error("Malformed request, priority must be 0, 1, or 2"), 400);
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
     *     path="/tmu/notices/(id)",
     *     summary="Edit TMU Notice. [Key]",
     *     description="Edit TMU Notice. Requires API Key, JWT, or Session Cookie (required roles:
    [N/A for API Key] ATM, DATM, TA, EC, INS)", produces={"application/json"}, tags={"solo"},
     *     security={"apikey","jwt","session"},
     *     produces={"application/json"}, tags={"tmu"},
     * @SWG\Parameter(name="id",type="integer",description="TMU Notice ID",in="path",required=true),
     * @SWG\Parameter(name="tmu_facility_id",type="string",description="TMU Map ID",in="formData"),
     * @SWG\Parameter(name="priority",type="string",description="Priority of notice
    (0: Low, 1: Standard, 2: Urgent)",in="formData"),
     * @SWG\Parameter(name="message",type="string",description="Notice content",in="formData"),
     * @SWG\Parameter(name="expire_date",type="string",description="Expiration time (YYYY-MM-DD H:i:s) - 'none' for no
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
     * ),
     *
     * @param \Illuminate\Http\Request $request
     * @param int $noticeId
     * @return \Illuminate\Http\JsonResponse
     */
    public function editNotice(Request $request, int $noticeId)
    {
        $facility = $request->input('facility', null);
        $startdate = $request->input('start_date', null);
        $expdate = $request->input('expire_date', null);
        $priority = $request->input('priority', null);
        $message = $request->input('message', null);

        $notice = TMUNotice::find($noticeId);
        if (!$notice) {
            return response()->api(generate_error("TMU Notice does not exist."), 400);
        }

        $fac = $notice->tmuFacility->parent ? $notice->tmuFacility->parent : $notice->tmuFacility->id; //ZXX
        if (Auth::check()) {
            if (!RoleHelper::isVATUSAStaff() && Auth::user()->facility != $fac) {
                return response()->api(generate_error("Forbidden. Cannot edit another ARTCC's TMU."), 403);
            }
            if (!(RoleHelper::isFacilityStaff() ||
                RoleHelper::isVATUSAStaff() ||
                RoleHelper::isInstructor())) {
                return response()->api(generate_error("Forbidden."), 403);
            }
        } else {
            if (!AuthHelper::validApiKeyv2($request->input('apikey', null), $fac)) {
                return response()->api(generate_error("Forbidden. Cannot edit another ARTCC's TMU."), 403);
            }
        }

        if ($facility) {
            $tmuFac = TMUFacility::find($facility);
            if (!$tmuFac) {
                return response()->api(generate_error("TMU facility does not exist"), 404);
            }
            $fac = $tmuFac->parent ? $tmuFac->parent : $tmuFac->id; //ZXX
            if (Auth::check()) {
                if (!RoleHelper::isVATUSAStaff() && Auth::user()->facility != $fac) {
                    return response()->api(generate_error("Forbidden. Cannot assign to another ARTCC's TMU."), 403);
                }
            } else {
                if (!AuthHelper::validApiKeyv2($request->input('apikey', null), $fac)) {
                    return response()->api(generate_error("Forbidden. Cannot assign to another ARTCC's TMU."), 403);
                }
            }

            $notice->tmuFacility()->associate($tmuFac);
        }

        if ($expdate == "none") {
            $expdate = null;
            $notice->expire_date = null;
        } else {
            if ($expdate) {
                try {
                    $cExpDate = Carbon::createFromFormat('Y-m-d H:i:s', $expdate);
                } catch (InvalidArgumentException $e) {
                    return response()->api(generate_error("Malformed request, invalid expire date format (Y-m-d H:i:s)"),
                        400);
                }

                if ($cExpDate->isPast()) {
                    return response()->api(generate_error("Malformed request, expire date cannot be in the past."),
                        400);
                }
                if ($cExpDate == $startdate ? $startdate : $notice->start_date) {
                    return response()->api(generate_error("Malformed request, expire date cannot be the same as start date."),
                        400);
                }
                if ($cExpDate < $startdate ? $startdate : $notice->start_date) {
                    return response()->api(generate_error("Malformed request, expire date cannot be before start date."),
                        400);
                }

                $notice->expire_date = $expdate;
            }
        }

        if ($startdate) {
            try {
                $cStartDate = Carbon::createFromFormat('Y-m-d H:i:s', $startdate);
            } catch (InvalidArgumentException $e) {
                return response()->api(generate_error("Malformed request, invalid start date format (Y-m-d H:i:s)"),
                    400);
            }
            if ($expdate != "none" && $cStartDate == $expdate ? $expdate : $notice->expire_date) {
                return response()->api(generate_error("Malformed request, expire date cannot be the same as start date."),
                    400);
            }
            if ($expdate != "none" && $cStartDate > $expdate ? $expdate : $notice->expire_date) {
                return response()->api(generate_error("Malformed request, start date cannot be after expire date."),
                    400);
            }
        }

        if ($priority) {
            if (!in_array(intval($priority), [1, 2, 3])) {
                return response()->api(generate_error("Malformed request, priority must be 0, 1, or 2"), 400);
            }
            $notice->priority = $priority;
        }

        if ($message) {
            $notice->message = $message;
        }

        if (!isTest()) {
            $notice->save();
        }

        return response()->ok();
    }

    /**
     * @SWG\Delete(
     *     path="/tmu/notices/(id)",
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
     * @param int                      $noticeId
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function removeNotice(Request $request, int $noticeId)
    {
        $notice = TMUNotice::find($noticeId);
        if (!$notice) {
            return response()->api(generate_error("TMU Notice does not exist."), 400);
        }

        $fac = $notice->tmuFacility->parent ? $notice->tmuFacility->parent : $notice->tmuFacility->id; //ZXX
        if (Auth::check()) {
            if (!RoleHelper::isVATUSAStaff() && Auth::user()->facility != $fac) {
                return response()->api(generate_error("Forbidden. Cannot delete another ARTCC's TMU notice."), 403);
            }
            if (!(RoleHelper::isFacilityStaff() ||
                RoleHelper::isVATUSAStaff() ||
                RoleHelper::isInstructor())) {
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

    /**
     * @SWG\Get(
     *     path="/tmu/notices/(tmufacid?)",
     *     summary="Get list of TMU Notices.",
     *     description="Get list of TMU Notices for either all of VATUSA or for the specified TMU Map ID.",
     *     produces={"application/json"},
     *     tags={"tmu"},
     *     @SWG\Parameter(name="tmufacid", in="path", type="string", description="TMU Map ID (optional)",
     *                                     required=false),
     *     @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(
     *             type="array",
     *             @SWG\Items(
     *                 type="object",
     *                 @SWG\Property(property="id",type="integer",description="TMU Notice ID"),
     *                 @SWG\Property(property="tmu_facility_id",type="string",description="TMU Map ID"),
     *                 @SWG\Property(property="priority",type="string",description="Priority of notice
     *                                                                                       (0:Low,1:Standard,2:Urgent)"),
     *                 @SWG\Property(property="message",type="string",description="Notice content"),
     *                 @SWG\Property(property="expire_date", type="string", description="Expiration time (YYYY-MM-DD
     *                                                       H:i:s)"),
     *                 @SWG\Property(property="start_date", type="string", description="Expiration time (YYYY-MM-DD
     *                                                       H:i:s)"),
     *             ),
     *         ),
     *     )
     * ),
     * @param \Illuminate\Http\Request $request
     *
     * @param string|null              $tmufacid
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getNotices(Request $request, string $tmufacid = null)
    {
        if ($tmufacid) {
            $tmuFac = TMUFacility::find($tmufacid);
            if (!$tmuFac) {
                return response()->api(generate_error("TMU Facility does not exist."), 400);
            }
            $notices = $tmuFac->tmuNotices()->get()->toArray();
        } else {
            $notices = TMUNotice::all()->toArray();
        }

        return response()->api(["notices" => $notices]);
    }
}