<?php
namespace App;

/**
 * Class KnowledgebaseQuestions
 * @package App
 *
 * @OA\Schema(
 *     type="object",
 *     @OA\Property(property="id", @OA\Schema(type="integer")),
 *     @OA\Property(property="category_id", @OA\Schema(type="integer")),
 *     @OA\Property(property="order", @OA\Schema(type="integer")),
 *     @OA\Property(property="question", @OA\Schema(type="string")),
 *     @OA\Property(property="answer", @OA\Schema(type="string")),
 *     @OA\Property(property="updated_by", @OA\Schema(type="integer")),
 *     @OA\Property(property="created_at", @OA\Schema(type="string")),
 *     @OA\Property(property="updated_at", @OA\Schema(type="string")),
 * )
 */
class KnowledgebaseQuestions extends Model
{
    protected $table = "knowledgebase_questions";

    public function category() {
        return $this->hasOne('App\KnowledgebaseCategories', 'id', 'category_id');
    }
}
