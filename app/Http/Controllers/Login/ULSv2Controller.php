<?php

namespace App\Http\Controllers\Login;

use App\Exceptions\FacilityNotFoundException;
use App\Exceptions\ReturnPathNotFoundException;
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
use Namshi\JOSE\Base64\Base64Encoder;

/**
 * Class ULSv2Controller
 * @package App\Http\Controllers\Login
 */
class ULSv2Controller extends Controller
{
    public function getLogin(Request $request)
    {
        $fac = $request->input('fac', null);
        $test = $request->has('test');

        $url = $request->input('url', 1);
        if ($request->has('dev') && !$request->has('url')) {
            $url = 2;
        }

        if (!$fac || !filter_var($url, FILTER_VALIDATE_INT)) {
            abort(400, "Malformed request");
        }

        $fac = strtoupper($fac);
        $facility = Facility::find($fac);
        if (!$facility || !$facility->active) {
            throw new FacilityNotFoundException("Invalid facility");
        }
        if (!$facility->returnPaths()->where('order', $url)->exists()) {
            throw new ReturnPathNotFoundException("Invalid return URL");
        }

        if($test && $facility->uls_jwk_dev == "")
            abort(400, "Sandbox JWK has not been generated.");
        if ($facility->uls_jwk == "" || ($test && $facility->uls_jwk_dev == "")) {
            abort(400,
                "Facility is not ready for ULSv2. Please contact the facility webmaster at " . strtolower($facility->id) . "-wm@vatusa.net");
        }

        session(compact('fac', 'url', 'test'));

        return redirect(env(!$test ? 'ULSv2_LOGIN' : 'SSO_RETURN_ULSv2'));
        //Testing: return test user using dev JWK key
    }

    public function getRedirect(Request $request)
    {
        $fac = $request->session()->has('fac') ? $request->session()->get('fac') : null;
        $url = $request->session()->has('url') ? $request->session()->get('url') : null;
        $test = $request->session()->has('test') ? $request->session()->get('test') : null;

        if (!$fac || !$url) {
            abort(400, "Malformed request");
        }

        $facility = Facility::where("id", $fac)->first();
        $redirect = ULSHelper::getReturnFromOrder($fac, $url);
        $facility_jwk = json_decode(!$test ? $facility->uls_jwk : $facility->uls_jwk_dev, true);

        if (!$test && !\Auth::check()) {
            return redirect("$redirect?cancel");
        }

        $algorithmManager = AlgorithmManager::create([
            new HS256(),
            new HS384(),
            new HS512()
        ]);
        $jwk = JWK::create($facility_jwk);
        $jsonConverter = new StandardConverter();
        $jwsBuilder = new JWSBuilder(
            $jsonConverter,
            $algorithmManager
        );

        $data = ULSHelper::generatev2Token(!$test ? \Auth::user() : factory(User::class)->make(['facility' => "ZXX"]),
            $facility);
        $payload = $jsonConverter->encode($data);
        $jws = $jwsBuilder->create()->withPayload($payload)->addSignature($jwk,
            ['alg' => $facility_jwk['alg']])->build();
        $serializer = new CompactSerializer($jsonConverter);
        $token = $serializer->serialize($jws, 0);

        $request->session()->forget("fac");
        $request->session()->forget("url");
        $request->session()->forget("test");

        if ($redirect) {
            return redirect("$redirect?token=$token");
        } else {
            abort(500, "Facility doesn't have a return URL configured");
        }
    }

    /**
     * @param Request $request
     *
     * @return string
     */
    public function getInfo(Request $request)
    {
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

        $data = json_decode(\Base64Url\Base64Url::decode($token), true);
        if (!$data) {
            \Log::info("Got invalid token $token");

            return response()->json(generate_error("Invalid token"), 400);
        }
        $signature = $data['sig'];
        unset($data['sig']);
        $verify_sig = ULSHelper::generatev2Signature($data);
        if ($signature !== $verify_sig['sig']) {
            return response()->json(['status' => 'Invalid token'], 401);
        }

        if ($data['exp'] < time()) {
            return response()->json(['status' => "Expired"], 410);
        }

        $user = User::find($data['sub']);
        $facility = Facility::find($data['aud']);       // Assumption, but not much risk here, checked by our signature anyway
        if ($data['sub'] == 999) {
            $user = factory(User::class)->make(['facility' => "ZXX"]);
        }
        $data = [
            'cid'       => $user->cid,
            'lastname'  => $user->lname,
            'firstname' => $user->fname,
            'email'     => $user->email,
            'rating'    => RatingHelper::intToShort($user->rating),
            'intRating' => $user->rating,
            'facility'  => [
                'id'   => $facility->id,
                'name' => $facility->name
            ],
            'roles'     => [],
            'visiting_facilities'  => $user->visits->toArray(),
        ];
        foreach (Role::where('cid', $user->cid)->where('facility', $facility->id)->get() as $role) {
            $data['roles'][] = $role->role;
        }

        return encode_json($data);
    }
}
