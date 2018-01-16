<?php
namespace App;

/**
 * Class KnowledgebaseCategories
 * @package App
 *
 * @SWG\Definition(
 *     type="object",
 *     @SWG\Property(property="id", type="integer"),
 *     @SWG\Property(property="name", type="integer"),
 *     @SWG\Property(property="questions", type="array", @SWG\Items(ref="#/definitions/KnowledgebaseQuestions")),
 *     @SWG\Property(property="created_at", type="string"),
 * )
 */
class KnowledgebaseCategories extends Model
{
    protected $table = "knowledgebase_categories";
    protected $appends = ["questions"];
    protected $hidden = ["updated_at"];

    public function getQuestionsAttribute() {
        return KnowledgebaseQuestions::where('category_id', $this->id)->orderBy('order', 'ASC')->get();
    }

    public function questions() {
        return $this->hasMany('App\KnowledgebaseQuestions', 'category_id', 'id');
    }

    public function reorder() {
        $qs = $this->questions()->orderBy("order")->get();
        $x = 1;
        foreach ($qs as $q) {
            $q->order = $x;
            $q->save();
            $x += 1;
        }
    }
}
