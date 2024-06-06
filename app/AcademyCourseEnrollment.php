<?php namespace App;

use Illuminate\Database\Eloquent\Model;

class AcademyCourseEnrollment extends Model {
    public static $STATUS_NOT_ENROLLED = 0;
    public static $STATUS_ENROLLED = 1;
    public static $STATUS_COMPLETED = 2;
    public static $STATUS_EXEMPT = 3;

    protected $table = 'academy_course_enrollment';
    public function course()
    {
        return $this->belongsTo(AcademyCourse::class, 'id', 'academy_course_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'cid', 'cid');
    }
}

