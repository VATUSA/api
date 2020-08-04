<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class OTSEvalPerfInd extends Model
{
    protected $table = "ots_evals_perf_indicators";

    public function perfcat()
    {
        return $this->belongsTo(OTSEvalPerfCat::class);
    }

    public function results()
    {
        return $this->hasMany(OTSEvalIndResult::class);
    }
}
