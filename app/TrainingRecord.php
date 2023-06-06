<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * Class TrainingRecord
 * @package App
 *
 * @OA\Schema(
 *     type="object",
 *     definition="trainingrecord",
 *     @OA\Property(property="id", type="integer", description="Record ID"),
 *     @OA\Property(property="student_id", type="integer", description="Student CID"),
 *     @OA\Property(property="instructor_id", type="integer", description="Instructor CID"),
 *     @OA\Property(property="session_date", type="string", description="Date and time of session"),
 *     @OA\Property(property="facility_id", type="string", description="Facility ID (ex. ZSE)"),
 *     @OA\Property(property="position", type="string", description="Position worked/trained on (ex. SEA_APP)"),
 *     @OA\Property(property="duration", type="string", description="Duration of session, HH:MM"),
 *     @OA\Property(property="movements", type="integer", description="Number of aircraft seen"),
 *     @OA\Property(property="score", type="integer", description="Overall score/rating out of 5"),
 *     @OA\Property(property="notes", type="string", description="Training notes content"),
 *     @OA\Property(property="location", type="integer", description="0 = Classroom; 1 = Live; 2 = Sweatbox"),
 *     @OA\Property(property="ots_status", type="integer", description="OTS Status: 0 = Not OTS, 1 = OTS Pass, 2 = OTS Fail, 3 = OTS Recommended"),
 *     @OA\Property(property="is_cbt", type="boolean", description="System - CBT Completion"),
 *     @OA\Property(property="solo_granted", type="boolean", description="Solo was granted during the session"),
 *     @OA\Property(property="modified_by", type="integer", description="Editor CID"),
 * )
 */
class TrainingRecord extends Model
{
    protected $dates = ['created_at', 'updated_at', 'session_date'];
    protected $casts = ['is_cbt'       => 'boolean',
                        'solo_granted' => 'boolean'
    ];

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id', 'cid');
    }

    public function instructor()
    {
        return $this->belongsTo(User::class, 'instructor_id', 'cid');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'modified_by', 'cid');
    }

    public function facility()
    {
        return $this->belongsTo(Facility::class);
    }

    public function otsEval()
    {
        return $this->hasOne(OTSEval::class);
        //Optional. No relationship if the eval is created independently.
        //On training record display, search for independent evals (denoted with *) by mapping
        //position to level (APP = S3).
    }

    public function resolveRouteBinding($value, $field = null)
    {
        return $this->where($this->getRouteKeyName(), $value)->first() ?? abort(404);
    }
}
