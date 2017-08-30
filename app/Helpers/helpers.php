<?php

use App\Action;

/**
 * Generate standardized JSON output
 *
 * @param array $data
 * @return string
 */
function encode_json($data) {
    return json_encode($data, JSON_HEX_APOS | JSON_NUMERIC_CHECK);
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
 * @param $msg
 * @return string
 */
function generate_error($msg) {
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
function isTest(Request $request) {
    if ($request->has('test')) {
        return true;
    }

    return false;
}

function randomPassword()
{
    $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890,./-=+_!@#$%^&*() {}[];:<>?';
    $pass = [];
    $alphaLength = strlen($alphabet) - 1;
    for ($i = 0; $i < 24; $i++) {
        $n = rand(0, $alphaLength);
        $pass[] = $alphabet[$n];
    }
    return implode($pass);
}
