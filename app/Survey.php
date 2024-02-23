<?php

namespace App;

/**
 * Class Survey
 * @package App
 *
 * @OA\Schema(
 *     type="object",
 *     @OA\Property(property="id", @OA\Schema(type="integer")),
 *     @OA\Property(property="facility", @OA\Schema(type="string")),
 *     @OA\Property(property="name", @OA\Schema(type="string")),
 *     @OA\Property(property="created_at", @OA\Schema(type="string"), description="Date added to database"),
 *     @OA\Property(property="updated_at", @OA\Schema(type="string")),
 * )
 */
class Survey extends Model
{
    public function questions() {
        return $this->hasMany("App\SurveyQuestion", "survey_id", "id");
    }
}
