<?php

namespace App\Http\Controllers\Login;

use App\Exceptions\FacilityNotFoundException;
use App\Facility;
use App\Helpers\RatingHelper;
use App\Helpers\RoleHelper;
use App\Helpers\ULSHelper;
use App\ULSToken;
use App\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Carbon\Carbon;

/**
 * Class ULSController
 * @package App\Http\Controllers\Login
 */
class ULSController extends Controller
{
    /**
     * @param Request $request
     * @throws FacilityNotFoundException
     */
    public function getLogin(Request $request) {
        if (!$request->has('fac')) {
            abort(400, "Malformed request");
        }

        $facility = Facility::find($request->get('fac'));
        if (!$facility->active) {
            throw new FacilityNotFoundException("Invalid facility");
        }

        session(['fac' => strtoupper($request->input('fac'))]);
        if ($request->has('dev')) {
            session(['dev' => true]);
        }

        header("Location: " . env('ULS_LOGIN'));
    }

    /**
     * @param Request $request
     */
    public function getRedirect(Request $request) {
        if (!$request->session()->has('fac')) {
            abort(400, "Malformed request");
        }

        if (!\Auth::check()) {
            abort(400, "Failed authentication");
        }
        $token = ULSHelper::generateToken($request->session()->get("fac"));
        $uls_token = ULSToken::where("token", $token)->first();
        if ($uls_token) {
            if ($uls_token->date->diffInSeconds(Carbon::Now()) >= 45 || $uls_token->cid == \Auth::user()->cid) {
                \DB::table("uls_tokens")->where("token", $token)->delete();
            } else {
                abort(500, "Unable to generate ULS Token");
            }
        }

        $uls_token = new ULSToken();
        $uls_token->token = $token;
        $uls_token->facility = $request->session()->get("fac");
        $uls_token->date = Carbon::now();
        $uls_token->ip = $_SERVER['REMOTE_ADDR'];
        $uls_token->cid = \Auth::user()->cid;
        $uls_token->expired = 0;
        $uls_token->save();

        $facility = Facility::where("id", $request->session()->get("fac"))->first();

        $redirect = null;
        if ($request->session()->has("dev")) {
            $request->session()->forget("dev");
            $redirect = $facility->uls_devreturn;
        } else {
            $redirect = $facility->uls_return;
        }
        $request->session()->forget("fac");

        if ($redirect) {
            header("Location: $redirect?token=$token");
        } else {
            abort(500,"Facility doesn't have a return URL configured");
        }
    }

    /**
     * @param Request $request
     * @return string
     */
    public function getInfo(Request $request) {
        if (!$request->has("token")) {
            abort(400, "Malformed request");
        }

        $format = "json";
        if ($request->has("format")) {
            if (strtolower($request->get("format")) == "xml") {
                $format = "xml";
            }
        }

        $token = ULSToken::where("token", $request->get("token"))->first();
        if (!$token || $token->date->diffInSeconds(Carbon::Now()) >= 45) {
            \DB::table("uls_tokens")->where("token", $token->token)->delete();
            abort(400, "invalid token");
        }
        $user = User::find($token->cid);
        $facility = Facility::find($user->facility);
        \DB::table("uls_tokens")->where("token", $token->token)->delete();

        $position['short'] = $position['long'] = "None";
        if (RoleHelper::has($user->cid, $facility->id, "ATM")) {
            $position['short'] = "ATM";
            $position['long'] = "Air Traffic Manager";
        }
        elseif (RoleHelper::has($user->cid, $facility->id, "DATM")) {
            $position['short'] = "DATM";
            $position['long'] = "Deputy Air Traffic Manager";
        }
        elseif (RoleHelper::has($user->cid, $facility->id, "TA")) {
            $position['short'] = "TA";
            $position['long'] = "Training Administrator";
        }
        elseif (RoleHelper::has($user->cid, $facility->id, "EC")) {
            $position['short'] = "EC";
            $position['long'] = "Event Coordinator";
        }
        elseif (RoleHelper::has($user->cid, $facility->id, "FE")) {
            $position['short'] = "FE";
            $position['long'] = "Facility Engineer";
        }
        if (RoleHelper::has($user->cid, $facility->id, "WM")) {
            $position['short'] = "WM";
            $position['long'] = "Webmaster";
        }

        $return = [
            "cid" => $user->cid,
            "name_first" => $user->fname,
            "name_last" => $user->lname,
            "email" => $user->email,
            "rating" => [
                "id" => $user->rating,
                "short" => RatingHelper::intToShort($user->rating),
                "long" => RatingHelper::intToLong($user->rating),
            ],
            "facility" => array(
                "id" => $user->facility,
                "short" => $user->facility,
                "long" => $user->facility->name,
                "position" => array (
                    "short" => $position['short'],
                    "long" => $position['long']
                ),
            ),
        ];
        if ($format == "xml") {
            header("Content-type: application/xml");
            $xml_info = new \SimpleXMLElement("<?xml version=\"1.0\"?><user></user>");
            arrayToXml($return, $xml_info);
            return $xml_info->asXML();
        } else {
            return encode_json($return);
        }
    }
}
