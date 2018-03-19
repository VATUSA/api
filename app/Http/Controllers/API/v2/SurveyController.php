<?php
namespace App\Http\Controllers\API\v2;


use App\Helpers\RoleHelper;
use App\SurveyAssignment;
use App\SurveySubmission;

class SurveyController
{
    /**
     * @SWG\Get(
     *     path="/survey/{id}",
     *     summary="(DONE) Get survey questions (CORS Restricted)",
     *     description="(DONE) Get survey questions (CORS Restricted)",
     *     produces={"application/json"},
     *     tags={"survey"},
     *     @SWG\Parameter(description="Survey Assignment ID", in="path", name="id", required=true, type="string"),
     *     @SWG\Response(
     *         response="404",
     *         description="Not found",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={{"application/json":{"status"="error","msg"="Not found"}}},
     *     ),
     *     @SWG\Response(
     *         response="309",
     *         description="Conflict (survey already completed)",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={{"application/json":{"status"="error","msg"="Conflict"}}},
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(
     *             type="object",
     *             @SWG\Property(property="status", type="string"),
     *             @SWG\Property(property="survey", ref="#/definitions/Survey"),
     *             @SWG\Property(property="items", type="array", @SWG\Items(ref="#/definitions/SurveyQuestion")),
     *         ),
     *     )
     * )
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
     * @SWG\Post(
     *     path="/survey/{id}",
     *     summary="Submit survey (CORS Restricted)",
     *     description="Submit survey (CORS Restricted)",
     *     produces={"application/json"},
     *     tags={"survey"},
     *     @SWG\Parameter(description="Survey Assignment ID", in="path", name="id", required=true, type="string"),
     *     @SWG\Parameter(name="data", in="formData", required=true, type="string"),
     *     @SWG\Response(
     *         response="400",
     *         description="Malformed request",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={{"application/json":{"status"="error","msg"="Malformed Request"}}},
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Not found",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={{"application/json":{"status"="error","msg"="Not found"}}},
     *     ),
     *     @SWG\Response(
     *         response="309",
     *         description="Conflict (survey already completed)",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={{"application/json":{"status"="error","msg"="Conflict"}}},
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(
     *             ref="#/definitions/OK"),
     *         ),
     *     )
     * )
     */

    public function postSurvey(Request $request, $id) {
        $assignment = SurveyAssignment::find($id);
        if (!$assignment || !$id) return response()->notfound();

        if ($assignment->completed) return response()->conflict();

        $_r = json_decode($request->input("data"));
        if (json_last_error() != false) return response()->malformed();

        $submission = new SurveySubmission();
        $submission->survey_id = $assignment->survey_id;
        $submission->facility = $assignment->facility;
        $submission->rating = $assignment->rating;
        $submission->data = $request->input("data");
        $submission->save();

        return response()->ok();
    }

    /**
     * @SWG\Post(
     *     path="/survey/{id}/assign/{cid}",
     *     summary="(DONE) Assign a survey to cid (CORS Restricted)",
     *     description="(DONE) Assign a survey to cid (CORS Restricted)",
     *     produces={"application/json"},
     *     tags={"survey"},
     *     @SWG\Parameter(description="Survey ID", in="path", name="id", required=true, type="integer"),
     *     @SWG\Parameter(description="CERT ID", in="path", name="cid", required=true, type="integer"),
     *     @SWG\Response(
     *         response="404",
     *         description="Not found",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={{"application/json":{"status"="error","msg"="Not found"}}},
     *     ),
     *     @SWG\Response(
     *         response="309",
     *         description="Conflict (survey already completed)",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={{"application/json":{"status"="error","msg"="Conflict"}}},
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(
     *             ref="#/definitions/OK"),
     *         ),
     *     )
     * )
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
