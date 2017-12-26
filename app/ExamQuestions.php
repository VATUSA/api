<?php
namespace App;

class ExamQuestions extends Model
{
    public $timestamps = false;
    protected $table = "exam_questions";

    public function exam()
    {
        return $this->belongsTo('App\Exam');
    }
}