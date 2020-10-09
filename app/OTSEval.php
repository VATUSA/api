<?php

namespace App;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Class OTSEval
 * @package App
 *
 * @SWG\Definition(
 *     type="object",
 *     definition="otseval",
 *     @SWG\Property(property="id", type="integer", description="Record ID"),
 *     @SWG\Property(property="filename", type="string", description="Filename in system"),
 *     @SWG\Property(property="training_record_id", type="integer", description="Training record DB ID, if exists"),
 *     @SWG\Property(property="student_id", type="integer", description="Student CID"),
 *     @SWG\Property(property="instructor_id", type="integer", description="Instructor CID"),
 *     @SWG\Property(property="rating_id", type="integer", description="DB ID of rating")
 * )
 */
class OTSEval extends Model
{
    protected $table = "ots_evals";

    public $timestamps = ['created_at', 'updated_at', 'exam_date'];

    public function trainingRecord()
    {
        return $this->belongsTo(TrainingRecord::class);
        //Optional. No relationship if the eval is created independently.
        //On training record display, search for independent evals (denoted with *) by mapping
        //position to level (APP = S3).
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id', 'cid');
    }

    public function instructor()
    {
        return $this->belongsTo(User::class, 'instructor_id', 'cid');
    }

    public function facility()
    {
        return $this->belongsTo(Facility::class);
    }

    public function form()
    {
        return $this->belongsTo(OTSEvalForm::class, 'form_id');
    }

    public function results()
    {
        return $this->hasMany(OTSEvalIndResult::class, 'eval_id');
    }

    /**
     * Eager-load all form elements.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithAll(Builder $query)
    {
        return $query->with(['form', 'form.perfcats', 'form.perfcats.indicators', 'form.perfcats.indicators.results']);
    }
}
