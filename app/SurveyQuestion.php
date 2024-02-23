<?php

namespace App;

/**
 * Class SurveyQuestion
 * @package App
 *
 * @OA\Schema(
 *     type="object",
 *     @OA\Property(property="id", @OA\Schema(type="integer")),
 *     @OA\Property(property="survey_id", @OA\Schema(type="integer")),
 *     @OA\Property(property="question", @OA\Schema(type="string")),
 *     @OA\Property(property="data", @OA\Schema(type="string")),
 *     @OA\Property(property="order", @OA\Schema(type="integer")),
 *     @OA\Property(property="created_at", @OA\Schema(type="string"), description="Date added to database"),
 *     @OA\Property(property="updated_at", @OA\Schema(type="string")),
 * )
 */
class SurveyQuestion extends Model
{
    public function getDataAttribute($value) {
        return json_decode($value);
    }
}
