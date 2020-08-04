<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class OTSEvalIndResult extends Model
{
    protected $table = "ots_evals_indicator_results";
    
    public function indicator() {
        return $this->belongsTo(OTSEvalPerfInd::class);
    }
    public function otsEval() {
        return $this->belongsTo(OTSEval::class);
    }
}
