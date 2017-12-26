<?php
namespace App;

/**
 * Class ExamResults
 * @package App
 *
 * @SWG\Definition(
 *     type="object",
 *     @SWG\Property(property="id", type="integer"),
 *     @SWG\Property(property="exam_id", type="integer"),
 *     @SWG\Property(property="exam_name", type="string"),
 *     @SWG\Property(property="cid", type="integer"),
 *     @SWG\Property(property="score", type="integer", description="Percentage times 100"),
 *     @SWG\Property(property="passed", type="integer", description="Integer representation of a boolean (1 = true, 0 = false)"),
 *     @SWG\Property(property="date", type="string", description="Date exam submitted"),
 * )
 */
class ExamResults extends Model
{
    public $timestamps = false;
    protected $table = "exam_results";
    protected $dates = ["date"];

    public function data() {
        return $this->hasMany('App\ExamResultsData','result_id','id');
    }

    public function exam() {
        return $this->hasOne('App\Exam','id','exam_id');
    }
}