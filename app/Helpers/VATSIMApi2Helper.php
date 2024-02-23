<?php

namespace App\Helpers;
use GuzzleHttp\Client;
use GuzzleHttp\Exception;

class VATSIMApi2Helper {

    private static function _url() {
        return env('VATSIM_API2_URL');
    }

    private static function _key() {
        return env('VATSIM_API2_KEY', null);
    }

    private static function _client(): Client {
        $key = VATSIMApi2Helper::_key();
        return new Client([
            'base_uri' => self::_url(),
            'headers' => ['Authorization' => "Token {$key}"],
            'User-Agent' => 'VATUSA/api +https://vatusa.net',
        ]);
    }

    static function fetchRatingHours($cid) {
        $path = "/v2/members/{$cid}/stats";
        $client = self::_client();
        try {
            $response = $client->get($path);
        } catch (Exception\GuzzleException $e) {
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
}