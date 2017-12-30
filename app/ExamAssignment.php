<?php
namespace App;


class ExamAssignment extends Model
{
    public $timestamps = false;
    protected $table = "exam_assignments";
    protected $dates = ['assigned_date', 'expire_date'];

    public function exam()
    {
        return $this->belongsTo('App\Exam','exam_id','id');
    }
}
