<?php
namespace App\Http\Controllers\API\v1;

use App\Facility;
use App\Helpers\FacilityHelper;
use App\Helpers\RatingHelper;
use App\Helpers\RoleHelper;
use App\User;

/**
 * Class FacilityController
 * @package App\Http\Controllers\API\v1
 */
class FacilityController
{
    /**
     * @param $facility
     * @param string $ext
     * @param null $limit (ignored)
     * @return string
     */
    public function getRoster($apikey, $facility, $ext = "json", $limit = null) {
        if (!$facility) {
            $fac = Facility::where('apikey', $apikey)->first();
            $facility = $fac->id;
        } else {
            $f = Facility::find($facility);
        }
        $error = 0;
        if (!$f || !$f->active) {
            return generate_error("Invalid Facility");
        }
        if (is_numeric($ext) && $limit == null) {
            $limit = $ext;
            $ext = "json";
        }
        $ext = strtolower($ext);
        if (!in_array($ext, ["xml", "json"])) {
            return generate_error("Invalid format");
        }
        if (!$error) {
            $return['status'] = "ok";
            $return['staff'] = FacilityHelper::getFacilityStaff($facility);
            $return['users'] = [];
            foreach (FacilityHelper::getRoster($facility) as $user) {
                $return['users'][] = [
                    'cid' => $user->cid,
                    'fname' => $user->fname,
                    'lname' => $user->lname,
                    'email' => $user->email,
                    'join_date' => $user->facility_join,
                    'promotion_eligible' => ($user->promotionEligible()) ? "1" : "0",
                    'rating' => $user->rating,
                    'rating_short' => RatingHelper::intToShort($user->rating)
                ];
            }
        }
        if ($ext == "xml") {
            $xmldata = new \SimpleXMLElement('<?xml version="1.0"?><api></api>');
            static::array_to_xml($return, $xmldata);
            return $xmldata->asXML();
        } elseif ($ext == "json") {
            return encode_json($return);
        }
    }

    /**
     * @param Request $request
     * @param $apikey
     * @return array
     */
    public function deleteRoster(Request $request, $apikey)
    {
        $fac = $request->fac;
        $cid = $request->cid;

        if ($fac == null) {
            $fac = Facility::where('apikey', $apikey)->first()->id;
        }
        $vars = [];
        parse_str(file_get_contents("php://input"), $vars);
        $return = [];

        $user = User::where('cid', $cid)->first();

        if (!isset($vars['msg']) && isset($vars['reason']))
            $vars['msg'] = $vars['reason'];

        if ($user == null) {
            return generate_error("User not found");
        } elseif ($user->facility != $fac) {
            return generate_error("User not in facility");
        } elseif (!isset($vars['by']) || !isset($vars['msg']) || $vars['msg'] == "") {
            return generate_error("By and msg arguments not optional");
        } else {
            if (RoleHelper::isSeniorStaff($vars['by'], $fac)) {
                if (!$request->has('test')) {
                    $user->removeFromFacility($vars['by'], $vars['msg']);
                }
                $return['status'] = "success";
                $return['msg'] = "User removed from facility.";
            } else {
                return generate_error("Access denied");
            }
        }

        return encode_json($return);
    }


    /**
     * @param $apikey
     * @param $cid
     * @return string
     *
     * @todo move in v2, this belongs somewhere more proper and with proper the EMR (Electronic Member Record) methods
     */
    public function getController($apikey, $cid) {
        $user = User::find($cid);
        if (!$user) {
            return generate_error("User not found");
        }

        $userArray['status'] = "success";
        $userArray['fname'] = $user->fname;
        $userArray['lname'] = $user->lname;
        $userArray['facility'] = $user->facility;
        $userArray['rating'] = $user->rating;
        $userArray['join_date'] = $user->facility_join;
        $userArray['last_activity'] = $user->lastactivity->format('Y-m-d H:i:s');
        return encode_json($userArray, JSON_HEX_APOS);
    }
}
