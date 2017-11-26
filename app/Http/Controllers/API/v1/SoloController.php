<?php

namespace App\Http\Controllers\API\v1;

use App\SoloCert;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

/**
 * Class SoloController
 * @package App\Http\Controllers\API\v1
 */
class SoloController extends Controller
{
    /**
     * @param $apikey
     * @param null $cid
     * @return string
     */
    public function getCerts($apikey, $cid = null) {
        if (!$cid) {
            return generate_error("CID field required", false);
        }

        $return = [
            'status' => "success",
            "solocerts" => []
        ];
        foreach (SoloCert::where('cid', $cid)->get() as $solo) {
            $return['solocerts'][] = [
                'position' => $solo->position,
                'expires' => $solo->expires,
            ];
        }

        return encode_json($return);
    }

    /**
     * @param Request $request
     * @param $apikey
     * @param $cid
     * @param $position
     * @return string
     */
    public function postCert(Request $request, $apikey, $cid, $position) {
        if (!$cid || !$position) {
            return response()->json(['status'=>'error','msg'=>"Invalid/missing field"], 400);
        }

        if (!preg_match("/^([A-Z0-9]{2,3})_(APP|CTR)$/", $position)) {
            return response()->json(['status'=>'error','msg'=>"Invalid position"], 400);
        }

        $exp = $request->input("expires", null);
        if (!$exp || !preg_match("/^\d{4}-\d{2}-\d{2}/", $exp)) {
            return generate_error("Malformed or missing field", false);
        }
        if (\Carbon\Carbon::createFromFormat('Y-m-d', $exp)->diffInDays() > 30) {
            return response()->json(['status'=>'error','msg'=>'Cannot be greater than 30 days'], 400);
        }

        if (!isTest()) {
            $solo = new SoloCert();
            $solo->cid = $cid;
            $solo->position = $position;
            $solo->expires = $exp;
            $solo->save();
        }
        $return = [
            'status' => "success"
        ];
        return encode_json($return);
    }

    /**
     * @param Request $request
     * @param $apikey
     * @param $cid
     * @param $position
     * @return string
     */
    public function deleteCert(Request $request, $apikey, $cid, $position) {
        if (!$cid || !$position) {
            return generate_error("Malformed or missing field", false);
        }

        if (!isTest()) {
            $solo = SoloCert::where('cid', $cid)->where('position', $position)->delete();
        }

        $return = [
            'status' => "success"
        ];
        return encode_json($return);
    }
}
