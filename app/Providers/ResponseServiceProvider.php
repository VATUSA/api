<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Routing\ResponseFactory;
use Request;
use App\Facility;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\Converter\StandardConverter;
use Jose\Component\Core\JWK;
use Jose\Component\Signature\Algorithm\HS256;
use Jose\Component\Signature\Algorithm\HS384;
use Jose\Component\Signature\Algorithm\HS512;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\Serializer\JSONFlattenedSerializer;

class ResponseServiceProvider extends ServiceProvider
{
    public function boot(ResponseFactory $factory) {
        $factory->macro('api', function ($data, $status = 200, $headers = []) use ($factory) {
            $showsig = false; $fjwk = null;
            if (Request::filled("f")) {
                $facility = Facility::find(Request::input("f"));
                if ($facility) {
                    $fjwk = $facility->apiv2_jwk;
                    $showsig = true;
                }
            } else if (isset($_SERVER['HTTP_ORIGIN'])) {
                $domain = extract_domain(parse_url($_SERVER['HTTP_ORIGIN'], PHP_URL_HOST));
                $facility = Facility::where('url', 'LIKE', "%$domain%")->first();
                if ($facility) {
                    $showsig = true;
                    $fjwk = $facility->apiv2_jwk;
                }
            }

            $sig = [];
            if ($showsig && $fjwk != null) {
                $algorithmManager = AlgorithmManager::create([
                    new HS256(), new HS384(), new HS512(),
                ]);

                $jwk = JWK::create(json_decode($facility->apiv2_jwk, true));

                $jsonConverter = new StandardConverter();

                $jwsBuilder = new JWSBuilder(
                    $jsonConverter,
                    $algorithmManager
                );

                $payload = $jsonConverter->encode($data);
                $jws = $jwsBuilder->create()->withPayload($payload)->addSignature($jwk, ['alg'=>json_decode($facility->apiv2_jwk, true)['alg']])->build();
                $serializer = new JSONFlattenedSerializer($jsonConverter);
                return $factory->make($serializer->serialize($jws, 0), $status, array_merge($headers, ['Content-Type' => 'application/json']));
            }

            return $factory->make(encode_json(array_merge($data, $sig)), $status, array_merge($headers, ['Content-Type' => 'application/json']));
        });
    }

    public function register() {
        //
    }
}
