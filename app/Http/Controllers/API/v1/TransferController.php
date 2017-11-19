<?php

namespace App\Http\Controllers\API\v1;

use App\Helpers\RatingHelper;
use App\Helpers\RoleHelper;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Facility;
use App\User;
use App\Transfer;

/**
 * Class TransferController
 * @package App\Http\Controllers\API\v1
 */
class TransferController extends Controller
{
    /**
     * @param $apikey
     * @param null $facility
     * @return string
     */
    public function getTransfers($apikey, $facility = null) {
        if (!$facility) {
            $facility = Facility::where('apikey', $apikey)->orWhere('api_sandbox_key', $apikey)->first();
        } else {
            $facility = Facility::find($facility);
        }

        $transfers = Transfer::where('to', $facility->id)->where('status', 0)->get();
        $return['status'] = "success";
        $return['transfers'] = [];
        foreach ($transfers as $transfer) {
            $userInfo = [];
            $userInfo['id'] = $transfer->id;
            $userInfo['cid'] = $transfer->cid;
            $userInfo['fname'] = $transfer->user->fname;
            $userInfo['lname'] = $transfer->user->lname;
            $userInfo['rating'] = $transfer->user->rating;
            $userInfo['rating_short'] = RatingHelper::intToShort($transfer->user->rating);
            $userInfo['email'] = $transfer->user->email;
            $userInfo['from_facility'] = $transfer->from;
            $userInfo['reason'] = $transfer->reason;
            $userInfo['submitted'] = $transfer->created_at->toDateString();
            $return['transfers'][] = $userInfo;
        }

        return encode_json($return);
    }

    /**
     * @param Request $request
     * @param $apikey
     * @param $id
     * @return string
     */
    public function postTransfer(Request $request, $apikey, $id) {
        $transfer = Transfer::find($id);
        $return = [];
        if (!$transfer) {
            return generate_error("Transfer not found", false);
        }
        $by = (int)$_POST['by'];
        $to = $transfer->to;
        if (!RoleHelper::isSeniorStaff($by, $to)) {
            return generate_error("Access denied", false);
        }
        if ($transfer->status > 0) {
            return generate_error("Transfer not in pending status", false);
        }

        if ($_POST['action'] == "reject") {
            if (isset($_POST['by']) && isset($_POST['reason'])) {
                if (!isTest($request)) {
                    $transfer->reject($by, $_POST['reason']);
                }
                $return['status'] = 'success';
            } else {
                return generate_error("Incomplete request", false);
            }
        } elseif ($_POST['action'] == "accept") {
            if (!isTest()) {
                $transfer->accept($by);
            }
            $return['status'] = "success";
        } else {
            return generate_error("Unknown action", false);
        }

        return encode_json($return);
    }
}
