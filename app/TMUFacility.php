<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TMUFacility extends Model
{
    protected $table = 'tmu_facilities';

    public function tmuNotices()
    {
        return $this->hasMany(TMUNotice::class, 'tmu_facility_id');
    }
}
