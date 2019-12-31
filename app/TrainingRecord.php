<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

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
