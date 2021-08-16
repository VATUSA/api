<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AcademyExamAssignment extends Model
{
    public function student()
    {
        return $this->belongsTo(User::class, 'student_id', 'cid');
    }

    public function instructor()
    {
        return $this->belongsTo(User::class, 'instructor_id', 'cid');
    }

    public function rating()
    {
        return $this->belongsTo(Rating::class);
    }
}
