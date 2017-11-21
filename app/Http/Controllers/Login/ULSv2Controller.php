<?php

namespace App\Http\Controllers\Login;

use App\Exceptions\FacilityNotFoundException;
use App\Facility;
use App\Helpers\RatingHelper;
use App\Helpers\RoleHelper;
use App\Helpers\ULSHelper;
use App\Role;
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
        if (!$facility || !$facility->active) {
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

        $facility = Facility::where("id", $request->session()->get("fac"))->first();
        $redirect = null;
        if ($request->session()->has("dev")) {
            $request->session()->forget("dev");
            $redirect = $facility->uls_devreturn;
        } else {
            $redirect = $facility->uls_return;
        }

        if (!\Auth::check()) {
            return redirect("$redirect?cancel");
        }

        $data = ULSHelper::generatev2Token(\Auth::user(), $facility);
        $token = urlencode(base64_encode($data));
        \Cache::put($token, "", 1);
        $token = $token . "." . hash_hmac('sha256', $data, $facility->uls_secret);

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
            return response()->json(['status' => 'Malformed request'], 400);
        }
        if (!\Cache::has(urldecode($request->input("token")))) {
            return response()->json(["status" => "Invalid token"], 401);
        }
        \Cache::forget(urldecode($request->input("token")));

        $data = json_decode(base64_decode(urldecode($request->input("token"))), true);
        $signature = $data['sig'];
        unset($data['sig']);
        $verify_sig = ULSHelper::generatev2Signature($data, env('ULS_SECRET'));
        if ($signature !== $verify_sig['sig']) {
            return response()->json(['status' => 'Invalid token'], 401);
        }

        if ($data['exp'] < time()) {
            return response()->json(['status' => "Expired"], 410);
        }

        $user = User::find($data['cid']);
        $facility = Facility::find($data['fac']);
        $data = [
            'cid' => $user->cid,
            'lastname' => $user->lname,
            'firstname' => $user->fname,
            'email' => $user->email,
            'rating' => RatingHelper::intToShort($user->rating),
            'intRating' => $user->rating,
            'facility' => [
                'id' => $facility->id,
                'name' => $facility->name
            ],
            'roles' => []
        ];
        foreach(Role::where('cid', $user->cid)->where('facility', $facility->id)->get() as $role) {
            $data['roles'][] = $role->role;
        }

        return encode_json($data);
    }
}
