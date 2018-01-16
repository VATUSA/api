<?php
namespace App;

/**
 * Class KnowledgebaseQuestions
 * @package App
 *
 * @SWG\Definition(
 *     type="object",
 *     @SWG\Property(property="id", type="integer"),
 *     @SWG\Property(property="category_id", type="integer"),
 *     @SWG\Property(property="order", type="integer"),
 *     @SWG\Property(property="question", type="string"),
 *     @SWG\Property(property="answer", type="string"),
 *     @SWG\Property(property="updated_by", type="integer"),
 *     @SWG\Property(property="created_at", type="string"),
 *     @SWG\Property(property="updated_at", type="string"),
 * )
 */
class KnowledgebaseQuestions extends Model
{
    protected $table = "knowledgebase_questions";

    public function category() {
        return $this->hasOne('App\KnowledgebaseCategories', 'id', 'category_id');
    }
}
