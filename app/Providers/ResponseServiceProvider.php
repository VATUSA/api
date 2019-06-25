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
    public function boot(ResponseFactory $factory)
    {
        $factory->macro('malformed', function ($data = []) use ($factory) {
            return response()->api(array_merge(generate_error("Malformed request"), $data), 400);
        });

        $factory->macro('conflict', function ($data = []) use ($factory) {
            return response()->api(array_merge(generate_error("Conflict"), $data), 409);
        });

        $factory->macro('notfound', function ($data = []) use ($factory) {
            return response()->api(array_merge(generate_error("Not found"), $data), 404);
        });

        $factory->macro('forbidden', function ($data = []) use ($factory) {
            return response()->api(array_merge(generate_error("Forbidden"), $data), 403);
        });

        $factory->macro('unauthenticated', function ($data = []) use ($factory) {
            return response()->api(array_merge(generate_error("Unauthorized"), $data), 401);
        });

        $factory->macro('ok', function ($data = []) use ($factory) {
            return response()->api(array_merge(['status' => 'OK'], $data), 200);
        });

        $factory->macro('api', function ($data, $status = 200, $headers = []) use ($factory) {
            $showsig = false;
            $fjwk = null;
            if (Request::filled("f")) {
                $facility = Facility::find(Request::input("f"));
                if ($facility) {
                    $fjwk = $facility->apiv2_jwk;
                    $showsig = true;
                }
            } else {
                if (isset($_SERVER['HTTP_ORIGIN'])) {
                    $domain = extract_domain(parse_url($_SERVER['HTTP_ORIGIN'], PHP_URL_HOST));
                    $facility = Facility::where('url', 'LIKE', "%$domain%")->first();
                    if ($facility) {
                        $showsig = true;
                        $fjwk = $facility->apiv2_jwk;
                    }
                }
            }

            $sig = [];
            if ($showsig && $fjwk != null) {
                $algorithmManager = AlgorithmManager::create([
                    new HS256(),
                    new HS384(),
                    new HS512(),
                ]);

                $facjwk = $facility->apiv2_jwk;
                if (isTest() && $facility->apiv2_jwk_dev) {
                    $facjwk = $facility->apiv2_jwk_dev;
                }

                $jwk = JWK::create(json_decode($facjwk, true));

                $jsonConverter = new StandardConverter();

                $jwsBuilder = new JWSBuilder(
                    $jsonConverter,
                    $algorithmManager
                );

                $payload = $jsonConverter->encode(array_merge(['testing' => isTest(), $data]));
                $jws = $jwsBuilder->create()->withPayload($payload)->addSignature($jwk,
                    ['alg' => json_decode($facjwk, true)['alg']])->build();
                $serializer = new JSONFlattenedSerializer($jsonConverter);

                return $factory->make($serializer->serialize($jws, 0), $status,
                    array_merge($headers, ['Content-Type' => 'application/json']));
            }

            return $factory->make(encode_json(array_merge($data, $sig, ['testing' => isTest()])), $status,
                array_merge($headers, ['Content-Type' => 'application/json']));
        });
    }

    public function register()
    {
        //
    }
}
