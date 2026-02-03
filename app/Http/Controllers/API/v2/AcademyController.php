<?php

namespace App\Http\Controllers\API\v2;


use App\AcademyExamAssignment;
use App\Action;
use App\Classes\VATUSAMoodle;
use App\Facility;
use App\Helpers\AuthHelper;
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
     * @OA\Get(
     *     path="/academy/identifiers",
     *     summary="Get of list course IDs.",
     *     description="Get list of Academy Rating course IDs.",
     *     tags={"academy"},
     *     @OA\Response(
     *         response="200",
     *         description="OK",
     *         @OA\Schema(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="BASIC", type="integer", description="Basic ATC/S1 exam course ID."),
     *                 @OA\Property(property="S2",type="integer",description="S2 exam course ID."),
     *                 @OA\Property(property="S3",type="integer",description="S3 exam course ID."),
     *                 @OA\Property(property="C1",type="integer",description="C1 exam course ID."),
     *             ),
     *         ),
     *     )
     * )
     * 
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getIdentifiers(Request $request)
    {
        return response()->api([
            'BASIC' => config('exams.BASIC.courseId'),
            'S2'    => config('exams.S2.courseId'),
            'S3'    => config('exams.S3.courseId'),
            'C1'    => config('exams.C1.courseId')
        ]);
    }

    /**
     * @OA\Post(
     *     path="/academy/enroll/{courseID}",
     *     summary="Enroll controller in course. [Key]",
     *     description="Enroll controller in ratings exam course (S2+). Requires Key,  Mentor (at or above requested rating),
     *     Instructor, or Senior Staff role.", tags={"academy"}, security={"session",
     *     "jwt"},
     *     @OA\RequestBody(
     *       @OA\MediaType(
     *        mediaType="application/x-www-form-urlencoded",
     *        @OA\Schema(
     *          @OA\Property(property="cid", type="integer", description="Controller CID"),
     *          @OA\Property(property="instructor", type="integer", description="Instructor CID [required for API Key]"),
     *        )
     *       )
     *     ),
     * @OA\Response(
     *         response="400",
     *         description="Malformed request",
     *         @OA\Schema(ref="#/components/schemas/error")
     *     ),
     * @OA\Response(
     *         response="401",
     *         description="Unauthorized",
     *         @OA\Schema(ref="#/components/schemas/error")
     *     ),
     * @OA\Response(
     *         response="403",
     *         description="Forbidden",
     *         @OA\Schema(ref="#/components/schemas/error")
     *     ),
     * @OA\Response(
     *         response="200",
     *         description="OK",
     *         @OA\Schema(ref="#/components/schemas/OK")
     *     )
     * )
     *
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

        if (Auth::check()) {
            $instructor = Auth::user();
        } else {
            $instructor = User::find($request->input('instructor'));
            if (!$instructor || !$instructor->flag_homecontroller) {
                return response()->api(
                    generate_error("Invalid instructor", true), 400
                );
            }
        }

        if (!AuthHelper::validApiKeyv2($request->input('apikey', null))
            && !Auth::check()) {
            return response()->forbidden();
        }
        if (!AuthHelper::validApiKeyv2($request->input('apikey', null))
            && !RoleHelper::isInstructor(Auth::user(), $user->facility)
            && !RoleHelper::isSeniorStaff(Auth::user(), $user->facility)
            && !RoleHelper::isMentor(Auth::user(), $user->facility)) {
            return response()->forbidden();
        }
        if (!AuthHelper::validApiKeyv2($request->input('apikey', null))
            && RoleHelper::isMentor(Auth::user(), $user->facility)
            && $user->rating >= Auth::user()->rating) {
            return response()->forbidden();
        }

        if (!in_array($courseId,
            [config('exams.S2.courseId'), config('exams.S3.courseId'), config('exams.C1.courseId')])) {
            return response()->api(
                generate_error("Invalid course ID - must be a ratings exam course above Basic.", true), 400
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
        $assignment->instructor_id = $instructor->cid;
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
            ->cc($instructor->email)
            ->queue(new AcademyRatingCourseEnrolled($assignment));

        $log = new Action();
        $log->to = $user->cid;
        $log->log = "Academy Rating Course (" . $assignment->course_name . ") " .
            " assigned by " . $instructor->fullname();
        $log->save();

        return response()->ok();
    }

    /**
     * @OA\Get(
     *     path="/academy/transcript/{cid}",
     *     summary="Retrieve the Academy transcript for a user. [Key]",
     *     description="Retrieve the Academy transcript for a user, including all attempts for each rating exam. The
    outer array keys are the ratings (ex. S1) and the inner arrays are the attempts. Requires at least an API key.",
     *      tags={"academy"},
     *     security={"apikey","session", "jwt"},
     * @OA\Response(
     *         response="400",
     *         description="Malformed request",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     *     * @OA\Response(
     *         response="404",
     *         description="Not found",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     * @OA\Response(
     *         response="401",
     *         description="Unauthorized",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     * @OA\Response(
     *         response="403",
     *         description="Forbidden",
     *         @OA\Schema(ref="#/components/schemas/error"),
     *         
     *     ),
     * @OA\Response(
     *         response="200",
     *         description="OK",
     *         @OA\Schema(ref="#/components/schemas/OK"),
     *     )
     * )
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\User                $user
     *
     * @return \Illuminate\Http\Response
     */
    public function getTranscript(Request $request, User $user)
    {
        $user->loadMissing('visits'); // Ensure user's visits are loaded
        if (Auth::check()) {
            Auth::user()->loadMissing('visits'); // Ensure authenticated user's visits are loaded
        }

        $validKeyVisit = $validKeyHome = false;
        if ($request->has('apikey')) {
            $validKeyHome = AuthHelper::validApiKeyv2($request->apikey, $user->facility);
            $facility = Facility::where('apikey', $request->apikey)->orWhere('api_sandbox_key',
                $request->apikey)->first();
            $validKeyVisit = $user->visits->contains('facility', $facility->id);
        }

        if (!$validKeyHome && !$validKeyVisit && !(Auth::check() && ($user->facility == Auth::user()->facility || (Auth::user()->visits && Auth::user()->visits->contains('facility', Auth::user()->facility))) && (RoleHelper::isMentor(Auth::user()) || RoleHelper::isInstructor(Auth::user()) || RoleHelper::isSeniorStaff(Auth::user())) || (Auth::check() && RoleHelper::isVATUSAStaff(Auth::user())))) {
            return response()->forbidden();
        }

        $results = [];
        $moodle = new VATUSAMoodle();
        $exams = [
            'BASIC' => config('exams.BASIC.id'),
            'S2'    => config('exams.S2.id'),
            'S3'    => config('exams.S3.id'),
            'C1'    => config('exams.C1.id')
        ];

        foreach ($exams as $rating => $id) {
            $result = [];
            $attempts = $moodle->getQuizAttempts($id, $user->cid);
            for ($i = 0; $i < count($attempts); $i++) {
                $result[$i] = [
                    'attempt'       => $attempts[$i]['attempt'],
                    'time_finished' => $attempts[$i]['timefinish'],
                    'grade'         => $attempts[$i]['grade']
                ];
            }

            $results[$rating] = $result;
        }


        return response()->api($results);
    }
}
