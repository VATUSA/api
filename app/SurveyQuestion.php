<?php

namespace App;

/**
 * Class SurveyQuestion
 * @package App
 *
 * @SWG\Definition(
 *     type="object",
 *     @SWG\Property(property="id", type="integer"),
 *     @SWG\Property(property="survey_id", type="integer"),
 *     @SWG\Property(property="question", type="string"),
 *     @SWG\Property(property="data", type="string"),
 *     @SWG\Property(property="order", type="integer"),
 *     @SWG\Property(property="created_at", type="string", description="Date added to database"),
 *     @SWG\Property(property="updated_at", type="string"),
 * )
 */
class SurveyQuestion extends Model
{
    //
}
