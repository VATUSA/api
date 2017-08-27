<?php

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

function generate_error($msg) {
    return [
        'status' => 'error',
        'msg' => $msg
    ];
}