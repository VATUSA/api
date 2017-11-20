<?php
namespace App;

use Illuminate\Database\Eloquent\Model;

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