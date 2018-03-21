<?php

namespace App;

/**
 * Class Survey
 * @package App
 *
 * @SWG\Definition(
 *     type="object",
 *     @SWG\Property(property="id", type="integer"),
 *     @SWG\Property(property="facility", type="string"),
 *     @SWG\Property(property="name", type="string"),
 *     @SWG\Property(property="created_at", type="string", description="Date added to database"),
 *     @SWG\Property(property="updated_at", type="string"),
 * )
 */
class Survey extends Model
{
    public function questions() {
        return $this->hasMany("App\SurveyQuestion", "survey_id", "id");
    }
}
