<?php

namespace App\Http\Controllers\API\v2;


use App\Classes\VATUSAMoodle;
use App\Helpers\RoleHelper;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Class AcademyController
 * @package App\Http\Controllers\API\v2
 */
class AcademyController extends APIController
{
    /**
     * @SWG\Get(
     *     path="/academy/enroll/{courseID}",
     *     summary="Enroll controller in course. [Private]",
     *     description="Enroll controller in ratings exam course. CORS Restricted.",
     *     produces={"application/json"},
     *     tags={"academy"},
     *     security={"session", "jwt"},
     *     @SWG\Parameter(name="cid", in="formData", type="integer", description="Controller CID"),
     * @SWG\Response(
     *         response="400",
     *         description="Malformed request",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={{"application/json":{"status"="error","message"="Invalid controller"}}}
     *     ),
     * @SWG\Response(
     *         response="401",
     *         description="Unauthorized",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Unauthorized"}},
     *     ),
     * @SWG\Response(
     *         response="403",
     *         description="Forbidden",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Forbidden"}},
     *     ),
     * @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(ref="#/definitions/OK"),
     *         examples={"application/json":{"status"="OK"}},
     *     )
     * )
     * @param \Illuminate\Http\Request $request
     * @param int                      $courseId
     *
     * @return \Illuminate\Http\Response
     */
    public function postEnroll(Request $request, int $courseId): Response
    {
        $user = User::find($request->input('cid'));
        if (!$user || !$user->flag_homecontroller) {
            return response()->api(
                generate_error("Invalid controller", true), 400
            );
        }

        if (!RoleHelper::isInstructor($user->facility) && !RoleHelper::isSeniorStaff($user->facility)) {
            return response()->forbidden();
        }

        if (!in_array($courseId,
            [config('exams.S2.courseId'), config('exams.S3.courseId'), config('exams.C1.courseId')])) {
            return response()->api(
                generate_error("Invalid course ID - must be a ratings exam course.", true), 400
            );
        }


        $moodle = new VATUSAMoodle();
        try {
            $result = $moodle->enrolUser($moodle->getUserId($user->cid), $courseId);
        } catch (\Exception $e) {
            return response()->api(
                generate_error("Unable to enroll user. " . $e->getMessage(), true), 500
            );
        }

        return response()->ok();
    }
}