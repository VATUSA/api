<?php
namespace App;

/**
 * Class ExamQuestions
 * @package App
 *
 * @SWG\Definition(
 *     type="object",
 *     @SWG\Property(property="id", type="integer"),
 *     @SWG\Property(property="exam_id", type="integer"),
 *     @SWG\Property(property="question", type="string"),
 *     @SWG\Property(property="type", type="integer", description="0-Multiple Choice, 1-True/False"),
 *     @SWG\Property(property="answer", type="string", description="Text of answer (True/False for T/F question type)"),
 *     @SWG\Property(property="alt1", type="string", description="Only for Mult. Choice, distractor"),
 *     @SWG\Property(property="alt2", type="string", description="Only for Mult. Choice, distractor"),
 *     @SWG\Property(property="alt3", type="string", description="Only for Mult. Choice, distractor"),
 * )
 */
class ExamQuestions extends Model
{
    public $timestamps = false;
    protected $table = "exam_questions";
    protected $hidden = ['notes'];

    public function exam()
    {
        return $this->belongsTo('App\Exam');
    }
}
