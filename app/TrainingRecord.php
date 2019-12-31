<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * Class TrainingRecord
 * @package App
 *
 * @SWG\Definition(
 *     type="object",
 *     @SWG\Property(property="id", type="integer", description="Record ID"),
 *     @SWG\Property(property="student_id", type="integer", description="Student CID"),
 *     @SWG\Property(property="instructor_id", type="integer", description="Instructor CID"),
 *     @SWG\Property(property="session_date", type="string", description="Date and time of session"),
 *     @SWG\Property(property="facility_id", type="string", description="Facility ID (ex. ZSE)"),
 *     @SWG\Property(property="position", type="string", description="Position worked/trained on (ex. SEA_APP)"),
 *     @SWG\Property(property="session_duration", type="string", description="Duration of session, HH:MM"),
 *     @SWG\Property(property="num_movements", type="integer", description="Number of aircraft seen"),
 *     @SWG\Property(property="score", type="integer", description="Overall score/rating out of 5"),
 *     @SWG\Property(property="notes", type="string", description="Training notes content"),
 *     @SWG\Property(property="session_location", type="integer", description="0 = Classroom; 1 = Live; 2 = Sweatbox"),
 *     @SWG\Property(property="isOTS", type="boolean", description="OTS Attempt"),
 *     @SWG\Property(property="isCBT", type="boolean", description="System - CBT Completion"),
 *     @SWG\Property(property="soloGranted", type="boolean", description="Solo was granted during the session"),
 * )
 */
class TrainingRecord extends Model
{
    protected $dates = ['created_at', 'updated_at', 'session_date'];
    
    public function student() {
        return $this->belongsTo(User::class,'student_id','cid');
    }

    public function instructor() {
        return $this->belongsTo(User::class,'instructor_id', 'cid');
    }

    public function facility() {
        return $this->belongsTo(Facility::class);
    }
}
