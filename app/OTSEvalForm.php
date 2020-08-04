<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class OTSEvalForm extends Model
{
    protected $table = "ots_evals_forms";

    public function rating()
    {
        return $this->belongsTo(Rating::class);
    }

    public function perfcats()
    {
        return $this->hasMany(OTSEvalPerfCat::class);
    }

    public function indicators()
    {
        return $this->hasManyThrough(OTSEvalPerfInd::class, OTSEvalPerfCat::class);
    }

    public function results()
    {
        return $this->hasManyThrough(OTSEvalIndResult::class, OTSEvalPerfInd::class);
    }
}
