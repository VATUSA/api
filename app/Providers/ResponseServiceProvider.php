<?php

namespace App\Providers;

use App\Helpers\FacilityHelper;
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

        $factory->macro("created", function($data = []) use ($factory) {
            return response()->api(array_merge(['status' => 'Created'], $data), 201);
        });

        $factory->macro('api', function ($data, $status = 200, $headers = []) use ($factory) {
            return $factory->make(encode_json(array_merge(['data' => $data], ['testing' => isTest()])), $status,
                array_merge($headers, ['Content-Type' => 'application/json']));
        });
    }

    public function register()
    {
        //
    }
}
