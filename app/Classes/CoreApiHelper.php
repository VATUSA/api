<?php

namespace App\Classes;

use App\CoreAPIModels\Transfer;
use App\CoreAPIModels\TransferHold;
use App\CoreAPIModels\Controller;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;

class CoreApiHelper
{

    private static function client() {
        return new Client(
            [
                "base_uri" => config('app.coreApiUrl'),
                "headers" => [
                    "Authorization" => "Token " . config('app.coreApiToken')
                ]
            ]
        );
    }

    private static function _request($method, $uri, $response_class, $data = null) {
        $client = self::client();
        if ($data !== null) {
            $options = [
                "body" => json_encode($data)
            ];
        } else {
            $options = [];
        }
        try {
            $response = $client->request($method, $uri, $options);
            $data = json_decode($response->getBody(), true);
            if (array_is_list($data)) {
                $out = [];
                foreach ($data as $item) {
                    $out[] = $response_class::fromAssoc($item);
                }
                return $out;
            }
            return self::_convert_response($data, $response_class);

        } catch (BadResponseException $e) {
            $response = $e->getResponse();
            $body = json_decode($response->getBody(), true);
            throw new CoreAPIHelperException($method, $uri, $response->getStatusCode(), $body['detail']);
        }
    }

    private static function _convert_response($data, $class) {
        $obj = new $class();
        foreach ($data as $key => $value) {
            $obj->{$key} = $value;
        }
        return $obj;
    }

    /**
     * @throws CoreAPIHelperException
     */
    public static function createTransferRequest(int    $cid,
                                                 string $facility,
                                                 string $reason,
                                                 int    $submitted_by_cid): Transfer {
        $data = [
            "cid" => $cid,
            "facility" => $facility,
            "reason" => $reason,
            "submitted_by_cid" => $submitted_by_cid
        ];
        return self::_request("POST", "/transfer/", Transfer::class, $data);
    }

    /**
     * @throws CoreAPIHelperException
     */
    public static function getPendingTransfers($facility = null) {
        if ($facility != null) {
            return self::_request("GET", "/transfer/pending/{$facility}", Transfer::class);
        } else {
            return self::_request("GET", "/transfer/pending/", Transfer::class);
        }
    }

    /**
     * @throws CoreAPIHelperException
     */
    public static function getControllerTransfers($cid) {
        return self::_request("GET", "/transfer/controller/{$cid}", Transfer::class);
    }

    /**
     * @throws CoreAPIHelperException
     */
    public static function getTransfer($id): Transfer {
        return self::_request("GET", "/transfer/{$id}", Transfer::class);
    }

    /**
     * @throws CoreAPIHelperException
     */
    public static function processTransferRequest(int $id, bool $approve, string $reason, int $admin_cid): Transfer {
        $data = [
            "approve" => $approve,
            "reason" => $reason,
            "admin_cid" => $admin_cid
        ];
        return self::_request("PUT", "/transfer/{$id}", Transfer::class, $data);
    }

    /**
     * @throws CoreAPIHelperException
     */
    public static function getControllerActiveHolds($cid) {
        return self::_request("GET", "/transfer/hold/controller/{$cid}", TransferHold::class);
    }

    /**
     * @throws CoreAPIHelperException
     */
    public static function createTransferHold(int $cid, string $hold, string $start_date, string $end_date,
                                              int $created_by_cid = null) {
        $data = [
            "cid" => $cid,
            "hold" => $hold,
            "start_date" => $start_date,
            "end_date" => $end_date,
            "created_by_cid" => $created_by_cid
        ];
        return self::_request("POST", "/transfer/hold", TransferHold::class, $data);
    }

    public static function updateTransferHold(int $hold_id, string $end_date = null, bool $clear_end_date = null,
                                              bool $is_released = null, int $admin_cid = null) {
        $data = [
          "end_date" => $end_date,
          "clear_end_date" => $clear_end_date,
          "is_released" => $is_released,
          "admin_cid", $admin_cid
        ];
        return self::_request("PUT", "/transfer/hold/{$hold_id}", TransferHold::class, $data);
    }

    public static function releaseTransferHold(int $hold_id, int $admin_cid = null) {
        return self::updateTransferHold($hold_id, admin_cid: $admin_cid);
    }
}