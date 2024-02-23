<?php

namespace App;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Class OTSEval
 * @package App
 *
 * @OA\Schema(
 *     type="object",
 *     schema="otseval",
 *     @OA\Property(property="id", @OA\Schema(type="integer"), description="Record ID"),
 *     @OA\Property(property="filename", @OA\Schema(type="string"), description="Filename in system"),
 *     @OA\Property(property="training_record_id", @OA\Schema(type="integer"), description="Training record DB ID, if exists"),
 *     @OA\Property(property="student_id", @OA\Schema(type="integer"), description="Student CID"),
 *     @OA\Property(property="instructor_id", @OA\Schema(type="integer"), description="Instructor CID"),
 *     @OA\Property(property="rating_id", @OA\Schema(type="integer"), description="DB ID of rating")
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
