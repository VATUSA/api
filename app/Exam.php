<?php
namespace App;

/**
 * Class Exam
 * @package App
 *
 * @SWG\Definition(
 *     type="object",
 *     @SWG\Property(property="id", type="integer"),
 *     @SWG\Property(property="facility_id", type="string"),
 *     @SWG\Property(property="name", type="string"),
 *     @SWG\Property(property="number", type="integer", description="Number to ask, 0 = all"),
 *     @SWG\Property(property="is_active", type="integer", description="integer representation of boolean (1=true,0=false)"),
 *     @SWG\Property(property="cbt_required", type="string", description="null = none, otherwise block id"),
 *     @SWG\Property(property="retake_period", type="integer", description="Number of days until automatic reassign (0=no auto-reassign)"),
 *     @SWG\Property(property="passing_score", type="integer", description="Percentage to pass times 100 (70%= .7 * 100=70)"),
 *     @SWG\Property(property="answer_visibility", type="string", description="Answer visibility, all = user and correct, all_passed = all when passed otherwise just user answers, user_only = only user selected options"),
 * )
 */
class Exam extends Model
{
    public $timestamps = false;
    protected $table = "exams";

    public function questions() {
        return $this->hasMany('App\ExamQuestions', 'exam_id');
    }

    public function facility() {
        return $this->hasOne('App\Facility', 'id', 'facility_id');
    }

    public function results() {
        return $this->hasMany('App\ExamResults', 'exam_id', 'id');
    }

    public function CBT() {
        return $this->hasOne("App\TrainingBlock", "id", "cbt_required");
    }

    public function CBTComplete(User $user = null) {
        if (!$this->cbt_required) return true;

        $block = TrainingBlock::find($this->cbt_required);
        if (!$block) { return true; }

        foreach ($block->chapters as $ch) {
            if (!$ch->isComplete($user)) return false;
        }

        return true;
    }
}
