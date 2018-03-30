<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Bucket
 * @package App
 *
 * @SWG\Definition(
 *     type="object",
 *     @SWG\Property(property="id", type="integer"),
 *     @SWG\Property(property="facility", type="string"),
 *     @SWG\Property(property="name", type="string"),
 *     @SWG\Property(property="arn", type="string"),
 *     @SWG\Property(property="access_key", type="string"),
 *     @SWG\Property(property="created_at", type="string"),
 *     @SWG\Property(property="updated_at", type="string"),
 * )
 */
class Bucket extends Model
{
    /**
     * @param int $cid
     * @param string $ip
     * @param string $entry
     * @return Bucketlog
     */
    public function log(int $cid, string $ip, string $entry) {
        $log = new Bucketlog();
        $log->bucket_id = $this->id;
        $log->facility = $this->facility;
        $log->cid = $cid;
        $log->ip = $ip;
        $log->log = $entry;
        $log->save();
        return $log;
    }

    public function arn() {
        return "arn:aws:s3:::$this->name";
    }
}
