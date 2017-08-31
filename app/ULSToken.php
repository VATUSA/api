<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ULSToken extends Model
{
    protected $table = "uls_tokens";
    public $incrementing = false;
    public $timestamps = false;
    protected $dates = [
        'date'
    ];
}
