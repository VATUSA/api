<?php

namespace App\Http\Controllers\API\v2;


use App\AcademyExamAssignment;
use App\Action;
use App\Classes\VATUSAMoodle;
use App\Helpers\EmailHelper;
use App\Helpers\Helper;
use App\Helpers\RoleHelper;
use App\Mail\AcademyRatingCourseEnrolled;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

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
            $uid = $moodle->getUserId($user->cid);
            $result = $moodle->enrolUser($uid, $courseId);
        } catch (\Exception $e) {
            return response()->api(
                generate_error("Unable to enroll user. " . $e->getMessage(), true), 500
            );
        }

        $assignment = new AcademyExamAssignment();
        $assignment->student_id = $user->cid;
        $assignment->instructor_id = Auth::user()->cid;
        $assignment->course_id = $courseId;
        $assignment->moodle_uid = $uid;
        $assignment->course_name = DB::connection('moodle')->table('course')
            ->where('id', $courseId)
            ->first()->fullname;
        if ($courseId == config('exams.S2.courseId')) {
            $assignment->quiz_id = config('exams.S2.id');
            $assignment->rating_id = Helper::ratingIntFromShort("S2");
        } elseif ($courseId == config('exams.S3.courseId')) {
            $assignment->quiz_id = config('exams.S3.id');
            $assignment->rating_id = Helper::ratingIntFromShort("S3");
        } else {
            $assignment->quiz_id = config('exams.C1.id');
            $assignment->rating_id = Helper::ratingIntFromShort("C1");
        }
        $assignment->save();

        Mail::to($user->email)
            ->cc(Auth::user()->email)
            ->queue(new AcademyRatingCourseEnrolled($assignment));

        $log = new Action();
        $log->to = $user->cid;
        $log->log = "Academy Rating Course (" . $assignment->course_name . ") " .
            " assigned by " . Auth::user()->fullname();
        $log->save();

        return response()->ok();
    }
}