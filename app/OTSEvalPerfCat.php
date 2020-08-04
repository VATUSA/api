<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class OTSEvalPerfCat extends Model
{
    protected $table = "ots_evals_perf_cats";

    public function form()
    {
        return $this->belongsTo(OTSEvalForm::class);
    }

    public function indicators()
    {
        return $this->hasMany(OTSEvalPerfInd::class);
    }

    public function results()
    {
        return $this->hasManyThrough(OTSEvalIndResult::class, OTSEvalPerfInd::class);
    }

}
