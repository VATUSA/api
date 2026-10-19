<?php

namespace App;

class TransfersBlocked extends Model
{
    protected $table = 'transfers_blocked';
    public $timestamps = false;

    public static function isBlocked($cid)
    {
        return static::where('cid', $cid)->exists();
    }
}
