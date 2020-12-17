<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class TMUNotice extends Model
{
    protected $dates = ['created_at', 'updated_at', 'expire_date', 'start_date'];
    protected $guarded = [];
    protected $hidden = ['tmu_facility_id'];
    protected $table = 'tmu_notices';
    protected $casts = ['is_delay' => 'boolean'];

    public function tmuFacility()
    {
        return $this->belongsTo(TMUFacility::class);
    }

    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->where('expire_date', '>=', Carbon::now('utc'));
            $q->orWhereNull('expire_date');
        })->where('start_date', '<=', Carbon::now());
    }
}
