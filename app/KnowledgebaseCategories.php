<?php
namespace App;

/**
 * Class KnowledgebaseCategories
 * @package App
 *
 * @OA\Schema(
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="name", type="integer"),
 *     @OA\Property(property="questions", type="array", @OA\Items(ref="#/components/schemas/KnowledgebaseQuestions")),
 *     @OA\Property(property="created_at", type="string"),
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
