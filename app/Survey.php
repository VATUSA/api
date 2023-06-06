<?php

namespace App;

/**
 * Class Survey
 * @package App
 *
 * @OA\Schema(
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="facility", type="string"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="created_at", type="string", description="Date added to database"),
 *     @OA\Property(property="updated_at", type="string"),
 * )
 */
class Survey extends Model
{
    public function questions() {
        return $this->hasMany("App\SurveyQuestion", "survey_id", "id");
    }
}
