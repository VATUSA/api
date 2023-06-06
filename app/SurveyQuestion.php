<?php

namespace App;

/**
 * Class SurveyQuestion
 * @package App
 *
 * @OA\Schema(
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="survey_id", type="integer"),
 *     @OA\Property(property="question", type="string"),
 *     @OA\Property(property="data", type="string"),
 *     @OA\Property(property="order", type="integer"),
 *     @OA\Property(property="created_at", type="string", description="Date added to database"),
 *     @OA\Property(property="updated_at", type="string"),
 * )
 */
class SurveyQuestion extends Model
{
    public function getDataAttribute($value) {
        return json_decode($value);
    }
}
