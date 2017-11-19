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
 * Class ULSv2Controller
 * @package App\Http\Controllers\Login
 */
class ULSv2Controller extends Controller
{
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

        return redirect(env('ULSv2_LOGIN'));
    }

    public function getRedirect(Request $request) {
        if (!$request->session()->has('fac')) {
            abort(400, "Malformed request");
        }

        if (!\Auth::check()) {
            abort(400, "Failed authentication");
        }

        $facility = Facility::where("id", $request->session()->get("fac"))->first();

        $data = ULSHelper::generatev2Token(\Auth::user(), $facility);
        $token = urlencode(base64_encode($data));
        $token = $token . "." . hash('sha256', $facility->uls_secret . '$' . $data);
        $redirect = null;
        if ($request->session()->has("dev")) {
            $request->session()->forget("dev");
            $redirect = $facility->uls_devreturn;
        } else {
            $redirect = $facility->uls_return;
        }
        $request->session()->forget("fac");

        if ($redirect) {
            return redirect("$redirect?token=$token");
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
        $data = json_decode(base64_decode(urldecode($request->input("token"))));
        $signature = $data['signature'];
        unset($data['signature']);
        $verify_sig = ULSHelper::generatev2Signature($data, env('ULS_SECRET'));
        if ($signature !== $verify_sig) {
            return response()->json(['status' => 'Failed'], 401);
        }

        //$user = User::find
    }
}
