<?php

use App\Action;

/**
 * Generate standardized JSON output
 *
 * @param array $data
 * @return string
 */
function encode_json($data) {
    return json_encode($data, JSON_NUMERIC_CHECK);
}

/**
 * Just to keep sane naming
 *
 * @param $data
 * @return mixed
 */
function decode_json($data) {
    return json_decode($data);
}

/**
 * @param string $msg
 * @param bool $asArray
 * @return string|array
 */
function generate_error($msg, $asArray = true) {
    if ($asArray) {
        return [
            'status' => 'error',
            'msg' => $msg
        ];
    }
    return encode_json([
        'status' => 'error',
        'msg' => $msg
    ]);
}

/**
 * @param $cid
 * @param $msg
 */
function log_action($cid, $msg) {
    $log = new Action();
    $log->from = 0;
    $log->to = $cid;
    $log->log = $msg;
    $log->save();
}

/**
 * @param Request $request
 * @return bool
 */
function isTest(Request $request = null) {
    if (!$request) { $request = request(); }
    if ($request->has('test') ||
        \App\Helpers\AuthHelper::isSandboxKey($request->input('apikey',
        null))) {
        return true;
    }

    return false;
}

/**
 * @param int $length 24
 * @return string
 */
function randomPassword($length = 24)
{
    $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
    $pass = [];
    $alphaLength = strlen($alphabet) - 1;
    for ($i = 0; $i < $length; $i++) {
        $n = rand(0, $alphaLength);
        $pass[] = $alphabet[$n];
    }
    return implode($pass);
}

/**
 * @param $data_array
 * @param $xml
 */
function arrayToXml($data_array, &$xml) {
    foreach($data_array as $key => $value) {
        if(is_array($value)) {
            if(!is_numeric($key)){
                $subnode = $xml->addChild("$key");
                arrayToXml($value, $subnode);
            }
            else{
                $subnode = $xml->addChild("item$key");
                arrayToXml($value, $subnode);
            }
        }
        else {
            $xml->addChild("$key",htmlspecialchars("$value"));
        }
    }
}

function extract_domain($domain) {
    if (preg_match("/(?P<domain>[a-z0-9][a-z0-9\-]{1,63}\.[a-z\.]{2,6})$/i", $domain, $matches)) {
        return $matches['domain'];
    } else {
        return $domain;
    }
}
