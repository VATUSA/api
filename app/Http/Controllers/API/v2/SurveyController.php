<?php
namespace App\Http\Controllers\API\v2;

use Illuminate\Http\Request;
use App\Helpers\RoleHelper;
use App\SurveyAssignment;
use App\SurveySubmission;
use App\Survey;
use App\User;

class SurveyController
{
    /**
     * @OA\Get(
     *     path="/survey/{id}",
     *     summary="Get survey questions. [Private]",
     *     description="Get survey questions (CORS Restricted).",
     *     tags={"survey"},
     *     @OA\Parameter(description="Survey Assignment ID", in="path", name="id", required=true, @OA\Schema(type="string")),
     *     @OA\Response(
     *         response="404",
     *         description="Not found",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     *     @OA\Response(
     *         response="309",
     *         description="Conflict (survey already completed)",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="OK",
     *         @OA\Schema(
     *             type="object",
     *             @OA\Property(property="status", @OA\Schema(type="string")),
     *             @OA\Property(property="survey", ref="#/components/schemas/Survey"),
     *             @OA\Property(property="items", type="array", @OA\Items(ref="#/components/schemas/SurveyQuestion")),
     *         ),
     *     )
     * )
     * @param \Illuminate\Http\Request $request
     * @param                          $id
     *
     * @return
     */

    public function getSurvey(Request $request, $id) {
        $survey = SurveyAssignment::find($id);
        if (!$id || !$survey) return response()->notfound();

        if ($survey->completed) return response()->conflict();

        $return = [];
        $return['survey'] = $survey->survey->toArray();
        $return['items'] = $survey->survey->questions->toArray();

        return response()->ok($return);
    }

    /**
     * @OA\Post(
     *     path="/survey/{id}",
     *     summary="Submit survey. [Private]",
     *     description="Submit survey (CORS Restricted).",
     *     tags={"survey"},
     *     @OA\Parameter(description="Survey Assignment ID", in="path", name="id", required=true, @OA\Schema(type="string")),
     *    @OA\RequestBody(@OA\MediaType(mediaType="application/x-www-form-urlencoded",@OA\Schema(
     *     @OA\Parameter(name="data",  required=true, @OA\Schema(type="string")),
     *    ))),
     *     @OA\Response(
     *         response="400",
     *         description="Malformed request",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Not found",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     *     @OA\Response(
     *         response="309",
     *         description="Conflict (survey already completed)",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="OK",
     *         @OA\Schema(
     *             ref="#/components/schemas/OK"),
     *         ),
     *     )
     * )
     * @param \Illuminate\Http\Request $request
     * @param                          $id
     *
     * @return
     */

    public function postSurvey(Request $request, $id) {
        $assignment = SurveyAssignment::find($id);
        if (!$assignment || !$id) return response()->notfound();

        if ($assignment->completed) return response()->conflict();

        $responses = json_decode($request->input("responses"), true);
        if (json_last_error() != false) return response()->malformed();

        for ($i = 0 ; isset($responses[$i]) ; $i++) {
            $submission = new SurveySubmission();
            $submission->survey_id = $assignment->survey_id;
            $submission->question_id = $responses[$i]['id'];
            $submission->response = $responses[$i]['response'];
            $submission->facility = $assignment->facility;
            $submission->rating = $assignment->rating;
            $submission->save();
        }

        $assignment->completed = 1;
        $assignment->save();

        return response()->ok();
    }

    /**
     * @OA\Post(
     *     path="/survey/{id}/assign/{cid}",
     *     summary="Assign a survey to cid. [Private]",
     *     description="Assign a survey to cid (CORS Restricted).",
     *     tags={"survey"},
     *     @OA\Parameter(description="Survey ID", in="path", name="id", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(description="CERT ID", in="path", name="cid", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response="404",
     *         description="Not found",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     *     @OA\Response(
     *         response="309",
     *         description="Conflict (survey already completed)",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="OK",
     *         @OA\Schema(
     *             ref="#/components/schemas/OK"),
     *         ),
     *     )
     * )
     * @param \Illuminate\Http\Request $request
     * @param                          $id
     * @param                          $cid
     *
     * @return \Illuminate\Http\Response
     * @throws \Exception
     */
    public function postSurveyAssign(Request $request, $id, $cid) {
        if (!\Auth::check()) return response()->unauthenticated();
        if (!RoleHelper::isVATUSAStaff(\Auth::user()->cid)) return response()->forbidden();

        $survey = Survey::find($id);
        if (!$survey) return response()->notfound();
        $user = User::find($cid);
        if (!$user) return response()->notfound();

        SurveyAssignment::assign($survey, $user);

        return response()->ok();
    }
}
