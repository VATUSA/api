<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Job extends Model
{
    public static $PENDING = "pending";
    public static $RUNNING = "running";
    public static $SUCCESS = "success";
    public static $FAILED = "failed";

    public static function create($type, $data) {
        if (is_array($data)) { $data = encode_json($data); }

        $job = new Job();
        $job->type = $type;
        $job->data = $data;
        $job->status = static::$PENDING;
        $job->save();
    }
}
