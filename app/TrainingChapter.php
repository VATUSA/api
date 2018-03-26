<?php

namespace App;

class TrainingChapter extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'training_chapters';

    public $timestamps = false;

    public function block() {
        return $this->hasOne('App\TrainingBlock', 'id', 'blockid');
    }

    public function isComplete(User $user = null) {
        if (!$user) {
            $user = \Auth::user();
        }

        return (TrainingProgress::where('cid', $user->cid)->where('chapterid', $this->id)->count() >= 1);
    }

    public function deleteChapter() {
        TrainingProgress::where('chapterid', $this->id)->delete();
        $this->delete();
        return;
    }

    public function getUrlAttribute($value) {
        if (!preg_match("~^http~", $value)) {
            return "https://docs.google.com/presentation/d/$value/embed?start=false&loop=false&delayms=60000";
        } else {
            return $value;
        }
    }
}
