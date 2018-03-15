<?php
namespace App\Http\Controllers\API\v2;


use App\Helpers\RoleHelper;
use App\SurveyAssignment;

class SurveyController
{
    /**
     * @SWG\Get(
     *     path="/survey/{id}",
     *     summary="(DONE) Get survey questions",
     *     description="(DONE) Get survey questions",
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
     *             type="array",
     *             @SWG\Items(
     *                 type="object",
     *                 @SWG\Property(property="type", type="string", description="Type of email (forward/full/static)"),
     *                 @SWG\Property(property="email", type="string", description="Email address"),
     *                 @SWG\Property(property="destination", type="string", description="Destination for email forwards")
     *             ),
     *         ),
     *         examples={
     *              "application/json":{
     *                      {"type":"forward","email":"test@vatusa.net","destination":"test2@vatusa.net"},
     *                      {"type":"full","email":"easy@vatusa.net"}
     *              }
     *         }
     *     )
     * )
     */

    /**
     * @SWG\Post(
     *     path="/survey/{id}/assign/{cid}",
     *     summary="(DONE) Assign a survey to cid",
     *     description="(DONE) Assign a survey to cid",
     *     produces={"application/json"},
     *     tags={"survey"},
     *     security={"jwt","session"},
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
