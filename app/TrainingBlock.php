<?php

namespace App;

/**
 * Class TrainingBlock
 * @package App
 *
 * @SWG\Definition(
 *     type="object",
 *     @SWG\Property(property="id", type="integer"),
 *     @SWG\Property(property="facility", type="string"),
 *     @SWG\Property(property="order", type="integer"),
 *     @SWG\Property(property="name", type="string"),
 *     @SWG\Property(property="level", type="string [Valid options: ALL, S1, S2, S3, C1, I1, Staff, Senior Staff]"),
 *     @SWG\Property(property="visible", type="integer"),
 * )
 */
class TrainingBlock extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'training_blocks';

    public $timestamps = false;

    public function chapters() {
        return $this->hasMany('App\TrainingChapter', 'blockid')->orderBy("order");
    }

    public function userCompleted($cid) {
        $chapters = $this->chapters()->get();
        foreach ($chapters as $chapter) {
            if (TrainingProgress::where('cid', $cid)->where('chapterid', $chapter->id)->count() < 1)
                return false;
        }

        return true;
    }

    public function deleteBlock() {
        $this->chapters()->deleteChapter();
        $this->delete();
    }
}
