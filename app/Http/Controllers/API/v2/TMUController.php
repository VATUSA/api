<?php

namespace App\Http\Controllers\API\v2;

use App\Helpers\AuthHelper;
use App\TMUFacility;
use App\TMUNotice;
use DateTime;
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
    public function addNotice(Request $request)
    {
        $facility = $request->input('facility', null); ///TMU Map Facility ID
        $expdate = $request->input('expire_date', null);
        $priority = $request->input('priority', 1); //Default: standard priority
        $message = $request->input('message', null);


        if (!$facility || !$expdate || !$message) {
            return response()->api(generate_error("Malformed request, missing fields"), 400);
        }

        $tmuFac = TMUFacility::find($facility);
        if (!$tmuFac->exists()) {
            return response()->api(generate_error("TMU facility does not exist"), 404);
        }

        $fac = $tmuFac->parent ? $tmuFac->parent : $tmuFac->id; //ZXX
        if (Auth::check()) {
            if (Auth::user()->facility != $fac) {
                return response()->api(generate_error("Forbidden. Cannot add notice for another ARTCC's TMU."), 403);
            }
        } else {
            if (!AuthHelper::validApiKeyv2($request->input('apikey', null), $fac)) {
                return response()->api(generate_error("Forbidden. Cannot add notice for another ARTCC's TMU."), 403);
            }
        }

        try {
            $cExpDate = Carbon::createFromFormat('Y-m-d H:i:s', $expdate);
        } catch (InvalidArgumentException $e) {
            return response()->api(generate_error("Malformed request, invalid expire date format (Y-m-d H:i:s)"), 400);
        }

        if (!$cExpDate->isPast()) {
            return response()->api(generate_error("Malformed request, expire date cannot be in the past"), 400);
        }

        if (!in_array(intval($priority), [0, 1, 2])) {
            return response()->api(generate_error("Malformed request, priority must be 0, 1, or 2"), 400);
        }

        if (!isTest()) {
            $notice = new TMUNotice;
            $notice->message = $message;
            $notice->priority = $priority;
            $notice->expire_date = $expdate;
            $facility->tmuNotices()->save($notice);
        }

        return response()->ok();
    }

    public function editNotice(Request $request, int $noticeId)
    {
        $facility = $request->input('facility', null);
        $expdate = $request->input('expire_date', null);
        $priority = $request->input('priority', 1); //default: standard priority
        $message = $request->input('message', null);

        $notice = TMUNotice::find($noticeId);
        if (!$notice->exists()) {
            return response()->api(generate_error("TMU Notice does not exist."), 400);
        }

        $fac = $notice->tmuFacility->parent ? $notice->tmuFacility->parent : $notice->tmuFacility->id; //ZXX
        if (Auth::check()) {
            if (Auth::user()->facility != $fac) {
                return response()->api(generate_error("Forbidden. Cannot edit another ARTCC's TMU."), 403);
            }
        } else {
            if (!AuthHelper::validApiKeyv2($request->input('apikey', null), $fac)) {
                return response()->api(generate_error("Forbidden. Cannot edit another ARTCC's TMU."), 403);
            }
        }

        if ($facility) {
            $tmuFac = TMUFacility::find($facility);
            if (!$tmuFac->exists()) {
                return response()->api(generate_error("TMU facility does not exist"), 404);
            }
            $fac = $tmuFac->parent ? $tmuFac->parent : $tmuFac->id; //ZXX
            if (Auth::check()) {
                if (Auth::user()->facility != $fac) {
                    return response()->api(generate_error("Forbidden. Cannot assign to another ARTCC's TMU."), 403);
                }
            } else {
                if (!AuthHelper::validApiKeyv2($request->input('apikey', null), $fac)) {
                    return response()->api(generate_error("Forbidden. Cannot assign to another ARTCC's TMU."), 403);
                }
            }

            $notice->tmuFacility()->associate($tmuFac);
        }

        if ($expdate) {
            try {
                $cExpDate = Carbon::createFromFormat('Y-m-d H:i:s', $expdate);
            } catch (InvalidArgumentException $e) {
                return response()->api(generate_error("Malformed request, invalid expire date format (Y-m-d H:i:s)"),
                    400);
            }

            if (!$cExpDate->isPast()) {
                return response()->api(generate_error("Malformed request, expire date cannot be in the past"), 400);
            }

            $notice->expire_date = $expdate;
        }

        if ($priority) {
            if (!in_array(intval($priority), [0, 1, 2])) {
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

    public function removeNotice(Request $request, int $noticeId)
    {
        $notice = TMUNotice::find($noticeId);
        if (!$notice->exists()) {
            return response()->api(generate_error("TMU Notice does not exist."), 400);
        }

        $fac = $notice->tmuFacility->parent ? $notice->tmuFacility->parent : $notice->tmuFacility->id; //ZXX
        if (Auth::check()) {
            if (Auth::user()->facility != $fac) {
                return response()->api(generate_error("Forbidden. Cannot delete another ARTCC's TMU notice."), 403);
            }
        } else {
            if (!AuthHelper::validApiKeyv2($request->input('apikey', null), $fac)) {
                return response()->api(generate_error("Forbidden. Cannot edit another ARTCC's TMU notice."), 403);
            }
        }

        if (!isTest()) {
            $notice->delete();
        }

        return reponse()->ok();
    }

    public function getNotices(Request $request, string $tmufacid = null)
    {
        //TODO:: in FacilityController, get all notices for facility itself
        if ($tmufacid) {
            $tmuFac = TMUFacility::find($tmufacid);
            if (!$tmuFac->exists()) {
                return response()->api(generate_error("TMU Facility does not exist."), 400);
            }
            $notices = $tmuFac->tmuNotices()->get()->toArray();
        } else {
            $notices = TMUNotice::all()->toArray();
        }

        return response()->api(["notices" => $notices]);
    }
}