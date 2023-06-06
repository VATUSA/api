<?php

namespace App;

use Ramsey\Uuid\Uuid;

/**
 * Class SurveyQuestion
 * @package App
 *
 * @OA\Schema(
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="survey_id", type="integer"),
 *     @OA\Property(property="facility", type="string", description="Facility user was in when assigned"),
 *     @OA\Property(property="rating", type="integer", description="Rating when assigned"),
 *     @OA\Property(property="completed", type="integer", description="Integer form of boolean"),
 *     @OA\Property(property="created_at", type="string", description="Date added to database"),
 *     @OA\Property(property="updated_at", type="string"),
 * )
 */
class SurveyAssignment extends Model
{
    public $incrementing = false;

    protected static function boot() {
        parent::boot();

        static::creating(function($model) {
            $model->{$model->getKeyName()} = $model->generateNewId();
        });
    }

    public function survey() {
        return $this->hasOne("App\Survey", "id", "survey_id");
    }

    public function generateNewId() {
        return Uuid::uuid1()->toString();
    }

    public function generateUrl() {
        return "https://survey.vatusa.net/$this->id";
    }

    public static function assign($survey,$user) {
        if (!($user instanceof User)) {
            $user = User::find($user);
            if (!$user) throw new \Exception("User not found.");
        }

        if (!($survey instanceof Survey)) {
            $survey = Survey::find($survey);
            if (!$survey) throw new \Exception("Survey not found.");
        }

        $a = new SurveyAssignment();
        $a->survey_id = $survey->id;
        $a->facility = $user->facility;
        $a->misc_data = json_encode([
            'cid' => $user->cid,
            'rating' => $user->rating
        ]);
        $a->save();

        $template = EmailTemplate::where('facility_id', $survey->facility)->where('template', 'survey' . $survey->id)->first();
        if ($template) {
            $data = $template->body;
            $template_file = resource_path("views/tmp_" . $a->id . ".blade.php");
            $fh = fopen($template_file, "w");
            fputs($fh, $data);
            fclose($fh);

            \Mail::to($user)->cc("vatusa12@vatusa.net")->send(new \App\Mail\SurveyAssignment($user, $survey, $a));
            unlink($template_file);
        }
        return $a;
    }

    public function getRatingAttribute() {
        return json_decode($this->data, true)['rating'];
    }
}
