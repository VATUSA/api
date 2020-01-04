<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TMUNotice extends Model
{
    public $dates = ['created_at', 'updated_at', 'expire_date', 'start_date'];
    protected $guarded = [];
    protected $hidden = ['tmu_facility_id'];
    protected $table = 'tmu_notices';

    public function tmuFacility()
    {
        return $this->belongsTo(TMUFacility::class);
    }
}
