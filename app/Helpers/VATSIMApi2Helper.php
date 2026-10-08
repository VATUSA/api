<?php

namespace App\Helpers;
use GuzzleHttp\Client;
use GuzzleHttp\Exception;

class VATSIMApi2Helper {

    private static function _url() {
        return config('services.vatsim_api2.url');
    }

    private static function _key() {
        return config('services.vatsim_api2.key', null);
    }

    private static function _identifier() {
        return config('services.vatsim_api2.identifier', null);
    }

    private static function _client(): Client {
        $key = VATSIMApi2Helper::_key();
        $identifier = VATSIMApi2Helper::_identifier();
        return new Client([
            'base_uri' => self::_url(),
            'headers' => [
                'Authorization' => "Token {$key}",
                'User-Agent' => 'VATUSA/api +https://vatusa.net',
                'x-identifier' => $identifier,
            ],
        ]);
    }

    static function fetchRatingHours($cid) {
        $path = "/v2/members/{$cid}/stats";
        $client = self::_client();
        try {
            $response = $client->get($path);
        } catch (Exception\GuzzleException $e) {
            echo "Error in VATSIM API2 fetchRatingHours: ".$e->getMessage();
            return null;
        }
        return json_decode($response->getBody(), true);
    }

    static function updateRating(int $cid, int $rating): bool {
        $path = "/members/{$cid}";
        $fullURL = VATSIMApi2Helper::_url() . $path;
        $key = VATSIMApi2Helper::_key();
        if ($key === null) {
            return false;
        }
        $data = [
            "id" => $cid,
            "rating" => $rating,
            "comment" => "VATUSA Rating Change Integration"
        ];
        $json = json_encode($data);
        $client = new Client(['headers' => ['Authorization' => "Token {$key}"]]);
        $response = $client->patch($fullURL, ['body' => $json]);
        return $response->getStatusCode() == 200;
    }

    /**
     * PATCH a member's subdivision on VATSIM. Pass null to clear it.
     * https://vatsim.dev/api/core-api/members-api-update-member-details/
     */
    static function updateSubdivision(int $cid, ?string $subdivisionId): bool {
        $key = VATSIMApi2Helper::_key();
        if ($key === null) {
            return false;
        }
        $client = new Client([
            'base_uri' => self::_url() . '/',
            'headers' => [
                'X-API-Key' => $key,
                'Accept' => 'application/json',
                'User-Agent' => 'VATUSA/api +https://vatusa.net',
                'x-identifier' => VATSIMApi2Helper::_identifier(),
            ],
        ]);
        $response = $client->patch("members/{$cid}", ['json' => [
            "id" => $cid,
            "subdivision_id" => $subdivisionId,
        ]]);
        return $response->getStatusCode() == 200;
    }
}