<?php
namespace App;

class ExamResults extends Model
{
    public $timestamps = false;
    protected $table = "exam_results";

    public function data() {
        return $this->hasMany('App\ExamResultsData','result_id','id');
    }

    public function exam() {
        return $this->hasOne('App\Exam','id','exam_id');
    }
}