<?php

namespace App;

class Facility extends Model
{
    protected $table = 'facilities';
    public $timestamps = false;
    public $incrementing = false;   // id is IATA of facility

    public function members()
    {
        return $this->hasMany('App\User','facility', 'id');
    }
}
