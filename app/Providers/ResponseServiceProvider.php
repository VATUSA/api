<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Routing\ResponseFactory;
use Request;
use App\Facility;

class ResponseServiceProvider extends ServiceProvider
{
    public function boot(ResponseFactory $factory) {
        $factory->macro('api', function ($data) use ($factory) {
            $showsig = false;
            if (Request::filled("f")) {
                $facility = Facility::find(Request::input("f"));
                if ($facility) {
                    $secret = $facility->apikey;
                    $showsig = true;
                }
            } else if (isset($_SERVER['HTTP_ORIGIN'])) {
                $domain = extract_domain(parse_url($_SERVER['HTTP_ORIGIN'], PHP_URL_HOST));
                $facility = Facility::where('url', 'LIKE', "%$domain%")->first();
                if ($facility) {
                    $showsig = true;
                    $secret = $facility->apikey;
                }
            }

            $sig = [];
            if ($showsig) {
                $sig['alg'] = env('API_SIGNATURE_ALGORITHM', 'sha256');
                $sig['sig'] = hash_hmac($sig['alg'], encode_json($data), $secret);
            }

            return $factory->make(encode_json(array_merge($data, $sig)));
        });
    }

    public function register() {
        //
    }
}
