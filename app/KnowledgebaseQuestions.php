<?php
namespace App;

/**
 * Class KnowledgebaseQuestions
 * @package App
 *
 * @OA\Schema(
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="category_id", type="integer"),
 *     @OA\Property(property="order", type="integer"),
 *     @OA\Property(property="question", type="string"),
 *     @OA\Property(property="answer", type="string"),
 *     @OA\Property(property="updated_by", type="integer"),
 *     @OA\Property(property="created_at", type="string"),
 *     @OA\Property(property="updated_at", type="string"),
 * )
 */
class KnowledgebaseQuestions extends Model
{
    protected $table = "knowledgebase_questions";

    public function category() {
        return $this->hasOne('App\KnowledgebaseCategories', 'id', 'category_id');
    }
}
