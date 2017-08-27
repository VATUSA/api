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
 * @return array
 */
function generate_error($msg) {
    return [
        'status' => 'error',
        'msg' => $msg
    ];
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