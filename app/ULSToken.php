<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ULSToken extends Model
{
    protected $table = "uls_tokens";
    public $incrementing = false;
    protected $primaryKey = 'token';
    public $timestamps = false;
    protected $dates = [
        'date'
    ];
}
