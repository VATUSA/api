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
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\Converter\StandardConverter;
use Jose\Component\Core\JWK;
use Jose\Component\Signature\Algorithm\HS256;
use Jose\Component\Signature\Algorithm\HS384;
use Jose\Component\Signature\Algorithm\HS512;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\Serializer\CompactSerializer;

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

        if ($facility->uls_jwk == "" || ($facility->uls_return == "" && $facility->uls_devreturn)) {
            abort(400, "Facility is not ready for ULSv2. Please contact the facility webmaster at " . strtolower($facility->id) . "-wm@vatusa.net");
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
        $facility_jwk = json_decode($facility->uls_jwk, true);

        if (!\Auth::check()) {
            return redirect("$redirect?cancel");
        }

        $algorithmManager = AlgorithmManager::create([
            new HS256(), new HS384(), new HS512()
        ]);
        $jwk = JWK::create(json_decode($facility->uls_jwk, true));
        $jsonConverter = new StandardConverter();
        $jwsBuilder = new JWSBuilder(
            $jsonConverter,
            $algorithmManager
        );

        $data = ULSHelper::generatev2Token(\Auth::user(), $facility);
        $payload = $jsonConverter->encode($data);
        $jws = $jwsBuilder->create()->withPayload($payload)->addSignature($jwk,['alg' => $facility_jwk['alg']])->build();
        $serializer = new CompactSerializer($jsonConverter);
        $token = $serializer->serialize($jws, 0);

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

        $t = $request->input("token");
        if (strpos($t, ".")) {
            $parts = explode(".", $t);
            $token = $parts[1];
        } else {
            $token = $t;
        }

        $data = json_decode(base64_decode(urldecode($request->input("token"))), true);
        $signature = $data['sig'];
        unset($data['sig']);
        $verify_sig = ULSHelper::generatev2Signature($data);
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
