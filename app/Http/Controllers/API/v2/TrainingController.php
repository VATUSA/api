<?php

namespace App\Http\Controllers\API\v2;

use App\Http\Controllers\Controller;
use App\Facility;
use App\Helpers\AuthHelper;
use App\Helpers\RoleHelper;
use App\OTSEval;
use App\OTSEvalForm;
use App\OTSEvalIndResult;
use App\Rating;
use App\Role;
use App\TrainingRecord;
use App\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Mews\Purifier\Facades\Purifier;

/**
 * Class TrainingController
 * @package App\Http\Controllers\API\v2
 * @author  Blake Nahin <vatusa12@vatusa.net>
 */
class TrainingController extends Controller
{
    /**
     * @SWG\Get(
     *     path="/training/record/{recordID}",
     *     summary="Get training record. [Key]",
     *     description="Get content of training record. Must have APIKey or be Senior Staff, Training Staff, or the
    student.",
     *     produces={"application/json"},
     *     tags={"training"},
     *     security={"session", "jwt", "apikey"},
     * @SWG\Response(
     *         response="400",
     *         description="Malformed request.",
     *         @SWG\Schema(ref="#/definitions/error")
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
     *        @SWG\Schema(ref="#/definitions/trainingrecord")
     *     )
     * )
     * Get individual training record.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\TrainingRecord      $record
     *
     * @return array
     */
    public function getTrainingRecord(Request $request, TrainingRecord $record)
    {
        //Get training record info
        // GET /training/record/8
        if ($this->canView($request, $record)) {

            return response()->api(array_merge($record->load('facility:id,name')->toArray(), [
                'instructor' => $record->instructor ? [
                    'cid'   => $record->instructor->cid,
                    'fname' => $record->instructor->fname,
                    'lname' => $record->instructor->lname,
                ] : [],
                'student'    => [
                    'cid'   => $record->student->cid,
                    'fname' => $record->student->fname,
                    'lname' => $record->student->lname,
                ],
                'editor'     => $record->editor ? [
                    'cid'   => $record->editor->cid,
                    'fname' => $record->editor->fname,
                    'lname' => $record->editor->lname,
                ] : []
            ]));
        }

        return response()->forbidden();

    }

    /**
     * @SWG\Get(
     *     path="/user/{cid}/training/records",
     *     summary="Get user's training records. [Key]",
     *     description="Get all user's training records. Must have APIKey or be Senior Staff, Training Staff, or the
    student.",
     *     produces={"application/json"},
     *     tags={"training", "user"},
     *     security={"session", "jwt", "apikey"},
     * @SWG\Response(
     *         response="400",
     *         description="Malformed request.",
     *         @SWG\Schema(ref="#/definitions/error")
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
     *        @SWG\Schema(ref="#/definitions/trainingrecord")
     *     )
     * )
     *
     * Get user's training records.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\User                $user
     *
     * @return array
     */
    public function getUserRecords(Request $request, User $user)
    {
        //Get records for a User
        // GET /user/1275302/training/records
        if ($this->canView($request, null, $user)) {
            return response()->api(TrainingRecord::where('student_id',
                $user->cid)->with('facility:id,name')->get()->toArray());
        }

        return response()->forbidden();

    }

    /**
     * @SWG\Get(
     *     path="/facility/{facility}/training/records",
     *     summary="Get facility's training records. [Key]",
     *     description="Get all facility's training records. Must have APIKey or be Senior Staff or Training Staff.",
     *     produces={"application/json"},
     *     tags={"training", "facility"},
     *     security={"session", "jwt", "apikey"},
     * @SWG\Response(
     *         response="400",
     *         description="Malformed request.",
     *         @SWG\Schema(ref="#/definitions/error")
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
     *        @SWG\Schema(ref="#/definitions/trainingrecord")
     *     )
     * )
     * Get all facility training records.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Facility            $facility
     *
     * @return array
     */
    public
    function getFacilityRecords(
        Request $request,
        Facility $facility
    ) {
        //Get records for a Facility
        // GET /facility/ZSE/training/records
        if ($this->canView($request, null, null, $facility)) {
            return response()->api(TrainingRecord::where('facility_id',
                $facility->id)->with('facility:id,name')->get()->toArray());
        }

        return response()->forbidden();

    }

    /**
     * @SWG\Get(
     *     path="/training/records",
     *     summary="Get all training records. [Private]",
     *     description="Get all training records. CORS Restricted.",
     *     produces={"application/json"},
     *     tags={"training"},
     *     security={"session", "jwt", "apikey"},
     * @SWG\Response(
     *         response="400",
     *         description="Malformed request.",
     *         @SWG\Schema(ref="#/definitions/error")
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
     *        @SWG\Schema(ref="#/definitions/trainingrecord")
     *     )
     * )
     *
     * Get All Records
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public
    function getAllRecords(
        Request $request
    ) {
        //Get all records #nofilter
        // GET /training/records
        if ($this->canView($request)) {
            return response()->api(TrainingRecord::with('facility:id,name')->all()->toArray());
        }

        return response()->forbidden();

    }

    /**
     * @SWG\Get(
     *     path="/training/otsEval/{recordID}",
     *     summary="Get OTS Eval content. [Private]",
     *     description="Get content of OTS Eval. CORS Restricted.",
     *     produces={"application/json"},
     *     tags={"training"},
     *     security={"session", "jwt"},
     * @SWG\Response(
     *         response="400",
     *         description="Malformed request.",
     *         @SWG\Schema(ref="#/definitions/error")
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
     *         description="OK"
     *     )
     * )
     * Get Eval Content
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\OTSEval             $eval
     *
     * @return string|null
     */
    public
    function getOTSEval(
        Request $request,
        OTSEval $eval
    ) {
        // [Not Implemented]
    }

    /**
     * @SWG\Get(
     *     path="/training/record/{recordID}/otsEval",
     *     summary="Get attached OTS eval. [Private]",
     *     description="Get content of OTS Eval attached to given record. CORS Restricted.",
     *     produces={"application/json"},
     *     tags={"training"},
     *     security={"session", "jwt"},
     * @SWG\Response(
     *         response="400",
     *         description="Malformed request.",
     *         @SWG\Schema(ref="#/definitions/error")
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
     *         description="OK"
     *     )
     * )
     * Get Attached Eval.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\TrainingRecord      $record
     *
     * @return string|null
     */
    public
    function getOTSTrainingEval(
        Request $request,
        TrainingRecord $record
    ) {
        //JSON of OTS Evaluation form from training record
        // GET /training/record/8/otsEval
        //if ($this->canView($request)) {
        //    return response()->api(['content' => $record->otsEval->getContent()]);
        // }

        // return response()->forbidden();

        // [Not Implemented]
    }

    /**
     * @SWG\Get(
     *     path="/user/{cid}/training/otsEvals",
     *     summary="Get user's OTS evaluations. [Private]",
     *     description="Get users training evaluations. CORS Restricted.",
     *     produces={"application/json"},
     *     tags={"training", "user"},
     *     security={"apikey","jwt","session"},
     * @SWG\Parameter(name="rating_id", in="query", type="integer", required=true, description="Filter by rating ID"),
     * @SWG\Response(
     *         response="400",
     *         description="Malformed request.",
     *         @SWG\Schema(ref="#/definitions/error")
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
     *         @SWG\Schema(ref="#/definitions/otseval")
     *     )
     * )
     *
     * Get OTS Evals for a given User.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\User                $user
     *
     * @return array|\Illuminate\Database\Eloquent\Builder
     */
    public
    function getUserOTSEvals(
        Request $request,
        User $user
    ) {
        //Get OTS Evals for a user.
        //  GET /user/1275302/training/otsEvals
        // QSP rating_id Filter by rating
        //Use when selecting which Eval to attach for promotion.
        $rating = $request->input('rating_id', null); //Rating ID
        if ($rating && !Rating::find($rating)) {
            return response()->api(generate_error("Invalid rating"), 400);
        }

        if ($this->canView($request) && !RoleHelper::isMentor()) {
            //Training staff except Mentors
            $return = OTSEval::where('student', $user->cid);
            if ($rating) {
                $return = $return->where('rating_id', $rating);
            }
            $return = $return->get()->toArray();

            return response()->api($return);
        }

        return response()->forbidden();
    }

    /**
     * @SWG\Get(
     *     path="/training/evals",
     *     summary="Get all OTS evaluations. [Private]",
     *     description="Get all OTS evaluations. This does not include the actual content. CORS Restricted.",
     *     produces={"application/json"},
     *     tags={"training"},
     *     security={"apikey","jwt","session"},
     * @SWG\Parameter(name="rating_id", in="query", type="integer", required=true, description="Filter by rating ID"),
     * @SWG\Response(
     *         response="400",
     *         description="Malformed request.",
     *         @SWG\Schema(ref="#/definitions/error")
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
     *         @SWG\Schema(ref="#/definitions/otseval")
     *     )
     * )
     *
     * Get OTS Evals for a given User.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array|\Illuminate\Database\Eloquent\Builder
     */
    public
    function getOTSEvals(
        Request $request
    ) {
        //Get all OTS Evals.
        //  GET /training/otsEvals
        // QSP rating_id Filter by rating
        //Use when selecting which Eval to attach for promotion.
        $rating = $request->input('rating_id', null); //Rating ID
        if ($rating && !Rating::find($rating)) {
            return response()->api(generate_error("Invalid rating"), 400);
        }

        if ($this->canView($request) && !RoleHelper::isMentor()) {
            //Training staff except Mentors
            if ($rating) {
                $return = OTSEval::where('rating_id', $rating)->get();
            } else {
                $return = OTSEval::all();
            }
            $return = $return->toArray();

            return response()->api($return);
        }

        return response()->forbidden();
    }

    /**
     * @SWG\Post(
     *     path="/user/{cid}/training/record",
     *     summary="Submit new training record. [Key]",
     *     description="Submit new training record. Requires API Key, JWT, or Session Cookie (required roles:
    [N/A for API Key] Senior Staff, Training Staff)", produces={"application/json"}, tags={"training"},
     *     security={"apikey","jwt","session"},
     * @SWG\Parameter(name="instructor_id", in="formData", type="integer", required=true, description="Instructor
    CID"),
     * @SWG\Parameter(name="session_date", in="formData", type="string", required=true, description="Session Date,
    YYYY-mm-dd HH:mm"),
     * @SWG\Parameter(name="position", in="formData", type="string", required=true, description="Position ID
    (XYZ_APP, ZZZ_CTR)"),
     * @SWG\Parameter(name="duration", in="formData", type="string", required=true, description="Session Duration,
    HH:mm"),
     * @SWG\Parameter(name="movements", in="formData", type="integer", required=false, description="Number of
    Movements"),
     * @SWG\Parameter(name="score", in="formData", type="integer", required=false, description="Session Score, 1-5"),
     * @SWG\Parameter(name="notes", in="formData", type="string", required=true, description="Session Notes"),
     * @SWG\Parameter(name="location", in="formData", type="integer", required=true, description="Session Location (0 =
    Classroom, 1 = Live, 2 = Sweatbox)"),
     * @SWG\Parameter(name="ots_status", in="formData", type="boolean", required=false, description="0 = Not OTS, 1 =
    OTS Pass, 2 = OTS Fail, 3 = OTS Recommended"),
     * @SWG\Parameter(name="is_cbt", in="formData", type="boolean", required=false, description="Record is a CBT
    Completion"),
     * @SWG\Parameter(name="solo_granted", in="formData", type="boolean", required=false, description="Solo endorsement
    was granted"),
     * @SWG\Response(
     *         response="400",
     *         description="Malformed request.",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={{"application/json":{"status"="error","message"="Invalid
     *         position"}},{"application/json":{"status"="error","message"="Invalid session date."}}},
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
     *         @SWG\Schema(
     *             type="object",
     *             @SWG\Property(property="status", type="string"),
     *             @SWG\Property(property="id", type="integer", description="DB ID of Record"),
     *         ),
     *         examples={"application/json":{"status"="OK", "id"=19, "testing": false}}
     *     )
     * )
     *
     * Add new record.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\User                $user
     *
     * @return \Illuminate\Http\JsonResponse
     * @throws \Throwable
     */
    public
    function postNewRecord(
        Request $request,
        User $user
    ) {
        //Submit new record
        // POST /user/1275302/training/record
        // Return training record id... resp()->ok(['id' => 19])
        if (!$this->canCreate($request, $user)) {
            return response()->forbidden();
        }

        //Input Data
        $studentId = $user->cid;
        $instructorId = $request->input("instructor_id", null);
        if (Auth::check() && (!$instructorId || ($instructorId && !RoleHelper::isSeniorStaff(Auth::user()->cid, Auth::user()->facility, true)))) {
            $instructorId = Auth::user()->cid;
        }
        $sessionDate = $request->input("session_date", null);
        $position = $request->input("position", null);
        if ($position) {
            $position = strtoupper($position);
        }
        $duration = $request->input("duration", null);
        $numMovements = $request->input("movements", null);
        $score = $request->input("score", null);
        $notes = $request->input("notes", null);
        $location = $request->input("location", null);
        $otsStatus = $request->input("ots_status", 0);
        $isCBT = $request->input("is_cbt", false);
        $soloGranted = $request->input("solo_granted", false);

        if ($request->input('facility', null)) {
            $facility = $request->input('facility');
        } else {
            if (Auth::check()) {
                //Authenticated
                $facility = Auth::user()->facility;
            } else {
                //Use API key.
                $facility = Facility::where('apikey', $request->apikey)
                    ->orWhere('api_sandbox_key', $request->apikey)->first()->id;
            }
        }

        //Validate
        if (!$studentId || (!$instructorId && !Auth::check()) || !$sessionDate || !$position || !$notes || is_null($location) || $location === "") {
            //Required Fields
            return response()->api(generate_error("Missing fields; see API documentation."), 400);
        }
        if ($numMovements && !is_numeric($numMovements)) {
            return response()->api(generate_error("Invalid number of movements, must be null or an integer."), 400);
        }
        if ($score && (!is_numeric($score) || !in_array(intval($score), [1, 2, 3, 4, 5]))) {
            return response()->api(generate_error("Invalid score, must be null or an integer and between 1-5."), 400);
        }
        if (!User::find($instructorId)) {
            return response()->api(generate_error("Invalid instructor."), 400);
        }
        if (!preg_match("/^([A-Z]{2,3})(_([A-Z0-9]{1,3}))?_(DEL|GND|TWR|APP|DEP|CTR|FSS)$/", $position)) {
            return response()->api(generate_error("Invalid position."), 400);
        }
        if (!in_array(intval($location), [0, 1, 2])) {
            return response()->api(generate_error("Invalid session location. Must be 0, 1, or 2."), 400);
        }
        if ($otsStatus == 1 && TrainingRecord::where([
                'ots_status' => 1,
                ['position', 'like', '%' . explode('_', $position)[1]],
                'student_id' => $studentId
            ])->exists()) {
            return response()->api(generate_error("The controller has an existing, passing OTS exam record for that position type."),
                400);
        }


        try {
            $sessionDate = Carbon::createFromFormat("Y-m-d H:i", $sessionDate);
        } catch (InvalidArgumentException $e) {
            return response()->api(generate_error("Invalid date; must be YYYY-mm-dd HH:MM."), 400);
        }

        try {
            $duration = Carbon::createFromFormat('H:i', $duration);
        } catch (InvalidArgumentException $e) {
            return response()->api(generate_error("Cannot create record. Invalid duration; must be HH:MM.", 400));
        }
        $duration = $duration->format("H:i:s");

        //Clean
        $notes = Purifier::clean($notes);

        //Submit
        $record = new TrainingRecord();
        $record->student_id = $studentId;
        $record->instructor_id = $instructorId ?? Auth::user()->cid;
        $record->session_date = $sessionDate;
        $record->facility_id = $facility;
        $record->position = $position;
        $record->duration = $duration;
        $record->movements = $numMovements;
        $record->score = $score;
        $record->notes = $notes;
        $record->location = $location;
        $record->ots_status = $otsStatus;
        $record->is_cbt = $isCBT;
        $record->solo_granted = $soloGranted;

        try {
            if (!isTest()) {
                try {
                    $record->saveOrFail();
                } catch (\Throwable $e) {
                    return response()->api(generate_error("Unable to save record.", 500));
                }
                //Check for evaluation
                if (in_array($otsStatus, [1, 2])) {
                    $eval = $user->evaluations()->where([
                        'exam_position' => $position,
                        'result'        => $otsStatus == 1,
                        'exam_date'     => $sessionDate->format('Y-m-d')
                    ]);
                    if ($eval->exists()) {
                        $otsEval = $eval->first();
                        $otsEval->training_record_id = $record->id;
                        $record->ots_eval_id = $otsEval->id;
                        $otsEval->save();
                        $record->save();
                    }
                    $user->promotionEligible();
                }
            }
        } catch (Exception $e) {
            return response()->api(generate_error("Unable to save record.", 500));
        }

        return response()->ok(['id' => isTest() ? null : $record->id]);
    }

    /**
     * @SWG\Post(
     *     path="/user/{cid}/training/otsEval",
     *     summary="Post new OTS Eval for a user. [Private]",
     *     description="Post new OTS Eval for a user. CORS Restricted.",
     *     produces={"application/json"},
     *     tags={"training", "user"},
     *     security={"session", "jwt", "apikey"},
     * @SWG\Response(
     *         response="400",
     *         description="Malformed request.",
     *         @SWG\Schema(ref="#/definitions/error")
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
     *         examples={"application/json":{"status"="OK","id"=1234,"testing":false}}
     *     )
     * )
     * Add OTS Evaluation
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\User                $user
     *
     * @throws \Exception
     */
    public
    function postOTSEval(
        Request $request,
        User $user
    ) {
        if (!Auth::user() || !RoleHelper::isInstructor(Auth::user()->cid, $user->facility)) {
            return response()->forbidden();
        }

        $form = $request->input('form', null);
        $position = $request->input('position', null);
        $date = $request->input('date', null);
        $result = $request->input('result', null);
        $notes = nl2br($request->input('notes', null));
        $signature = $request->input('signature', null);
        $indicators = $request->input('indicators', null);

        $form = OTSEvalForm::find($form);
        if (!$form) {
            return response()->api(generate_error("Invalid evaluation form."), 400);
        }

        if ($form->rating_id !== $user->rating + 1 || !$user->promotionEligible()) {
            return response()->api(generate_error("The user is ineligible for this evaluation."), 400);
        }

        if (!$position || !preg_match('/^([A-Z]{2,3})(_([A-Z]{1,3}))?_(TWR|APP|CTR)$/', $position)) {
            return response()->api(generate_error("Invalid position."), 400);
        }
        try {
            $examDate = Carbon::createFromFormat("Y-m-d", $date);
        } catch (InvalidArgumentException $e) {
            return response()->api(generate_error("Invalid date, must be YYYY-mm-dd."), 400);
        }
        if (is_null($result)) {
            return response()->api(generate_error("No result sent."), 400);
        }
        if (!$signature || count(explode(',', $signature)) != 2) {
            return response()->api(generate_error("No signature sent."), 400);
        }
        if (!$indicators || !is_array($indicators) || count($indicators) !== $form->indicators()->where('header_type',
                '!=', 1)->count()) {
            return response()->api(generate_error("Invalid indicator results."), 400);
        }

        $record = TrainingRecord::where([
            'student_id' => $user->cid,
            'ots_status' => $result ? $result : 2,
            ['position', 'like', '%' . explode('_', $position)[1]],
            [\DB::raw('DATE(session_date)'), '=', $date]
        ])->orderBy('created_at', 'desc');

        if ($record->exists()) {
            $record = $record->first();
            $recordId = $record->id;
        } else {
            $recordId = null;
        }

        $eval = new OTSEval();
        $eval->training_record_id = $recordId;
        $eval->student_id = $user->cid;
        $eval->instructor_id = Auth::user()->cid;
        $eval->exam_date = $date;
        $eval->facility_id = $user->facility;
        $eval->exam_position = strtoupper($position);
        $eval->form_id = $form->id;
        $eval->signature = $signature;
        $eval->notes = $notes;
        $eval->result = $result;
        try {
            $eval->saveOrFail();
        } catch (\Throwable $e) {
            return response()->api(generate_error("Unable to save submission."), 400);
        }

        if ($recordId) {
            $record->ots_eval_id = $eval->id;
            try {
                $record->saveOrFail();
            } catch (\Throwable $e) {
                return response()->api(generate_error("Unable to save submission. $e"), 400);
            }
        }

        foreach ($indicators as $k => $v) {
            $indResult = new OTSEvalIndResult();
            $indResult->perf_indicator_id = $v['id'];
            $indResult->eval_id = $eval->id;
            $indResult->result = $v['value'] ?? 0;
            $indResult->comment = strlen($v['comment']) ? $v['comment'] : null;
            try {
                $indResult->saveOrFail();
            } catch (\Throwable $e) {
                $eval->delete();

                return response()->api(generate_error("Unable to save submission. $e"), 400);
            }
        }

        $user->promotionEligible();

        return response()->ok(['id' => $eval->id]);
    }

    /**
     * @SWG\Put(
     *     path="/training/record/{record}",
     *     summary="Edit training record. [Key]",
     *     description="Edit training record. Requires API Key, JWT, or Session Cookie (required roles:
    [N/A for API Key] Senior Staff, Training Staff)",
     *     produces={"application/json"},
     *     tags={"training"},
     *     security={"apikey","jwt","session"},
     * @SWG\Parameter(name="session_date", in="formData", type="string", description="Session Date, YY-mm-dd HH:mm"),
     * @SWG\Parameter(name="position", in="formData", type="string", description="Position ID
    (XYZ_APP, ZZZ_CTR)"),
     * @SWG\Parameter(name="duration", in="formData", type="string", description="Session Duration, HH:mm"),
     * @SWG\Parameter(name="movements", in="formData", type="integer", description="Number of Movements"),
     * @SWG\Parameter(name="score", in="formData", type="integer", description="Session Score, 1-5"),
     * @SWG\Parameter(name="notes", in="formData", type="string", description="Session Notes"),
     * @SWG\Parameter(name="location", in="formData", type="integer", description="Session Location (0 = Classroom, 1 =
    Live, 2 = Sweatbox)"),
     * @SWG\Parameter(name="ots_status", in="formData", type="boolean", required=false, description="0 = Not OTS, 1 =
    OTS Pass, 2 = OTS Fail, 3 = OTS Recommended"),
     * @SWG\Parameter(name="solo_granted", in="formData", type="boolean", description="Solo endorsement was granted"),
     * @SWG\Response(
     *         response="400",
     *         description="Malformed request.",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={{"application/json":{"status"="error","message"="Invalid
     *         position"}},{"application/json":{"status"="error","message"="Invalid session date."}}},
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
     *         @SWG\Schema(
     *             type="object",
     *             @SWG\Property(property="status", type="string"),
     *             @SWG\Property(property="id", type="integer", description="DB ID of Record"),
     *         ),
     *         examples={"application/json":{"status"="OK", "testing": false}}
     *     )
     * )
     *
     * Edit record.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\TrainingRecord      $record
     */
    public
    function editRecord(
        Request $request,
        TrainingRecord $record
    ) {
        //Owner instructor and senior staff
        //PUT /training/record/8

        if (!$this->canModify($request, $record)) {
            return response()->forbidden();
        }

        if (in_array($record->ots_status, [1, 2]) && !RoleHelper::isVATUSAStaff()) {
            return response()->api(generate_error("Unable to edit record because it is an OTS exam. Please contact VATUSA3 or 13 for assistance."),
                500);
        }

        //Input Data
        $sessionDate = $request->input("session_date", $record->session_date);
        if (!$sessionDate) {
            $sessionDate = $record->session_date;
        }
        $position = strtoupper($request->input("position", $record->position));
        if (!$position) {
            $position = $record->position;
        }
        $duration = $request->input("duration", substr($record->duration, 0, 5));
        if (!$duration) {
            $duration = substr($record->duration, 0, 5);
        }
        $numMovements = $request->input("movements", $record->movements);
        if (is_null($numMovements)) {
            $numMovements = $record->movements;
        }
        $score = $request->input("score", $record->score);
        if (!$score) {
            $score = $record->score;
        }
        $notes = $request->input("notes", $record->notes);
        if (!$notes) {
            $notes = $record->notes;
        }
        $location = $request->input("location", $record->location);
        if (!$location) {
            $location = $record->location;
        }
        $otsStatus = $request->input("ots_status", $record->ots_status);
        if (!$otsStatus) {
            $isOTS = $record->ots_status;
        }
        $soloGranted = $request->input("solo_granted", $record->solo_granted);
        if ($soloGranted == "") {
            $soloGranted = $record->solo_granted;
        }

        //Validate
        if ($numMovements && !is_numeric($numMovements)) {
            return response()->api(generate_error("Invalid number of movements, must be null or an integer."), 400);
        }
        if ($score && (!is_numeric($score) || !in_array(intval($score), [1, 2, 3, 4, 5]))) {
            return response()->api(generate_error("Invalid score, must be null or an integer and between 1-5"), 400);
        }
        if (!preg_match("/^([A-Z]{2,3})(_([A-Z]{1,3}))?_(DEL|GND|TWR|APP|DEP|CTR|FSS)$/", $position)) {
            return response()->api(generate_error("Invalid position."), 400);
        }
        if (!in_array(intval($location), [0, 1, 2])) {
            return response()->api(generate_error("Invalid session location. Must be 0, 1, or 2."), 400);
        }

        if (!($sessionDate instanceof Carbon)) {
            try {
                $sessionDate = Carbon::createFromFormat("Y-m-d H:i", $sessionDate);
            } catch (InvalidArgumentException $e) {
                return response()->api(generate_error("Invalid date; must be YYYY-mm-dd HH:MM."), 400);
            }
        }

        if (!($duration instanceof Carbon)) {
            try {
                $duration = Carbon::createFromFormat('H:i', $duration);
            } catch (InvalidArgumentException $e) {
                return response()->api(generate_error("Cannot edit record. Invalid duration; must be HH:MM.", 400));
            }
        }
        $duration = $duration->format("H:i:s");

        //Clean
        $notes = Purifier::clean(nl2br($notes));

        //Submit
        $record->session_date = $sessionDate;
        $record->position = $position;
        $record->duration = $duration;
        $record->movements = $numMovements;
        $record->score = $score;
        $record->notes = $notes;
        $record->location = $location;
        $record->ots_status = $otsStatus;
        $record->solo_granted = $soloGranted;

        try {
            if (!isTest()) {
                $record->saveOrFail();
                //Check for evaluation
                if (in_array($otsStatus, [1, 2])) {
                    $eval = $record->student->evaluations()->where([
                        'exam_position' => $position,
                        'result'        => $otsStatus == 1,
                        'exam_date'     => $sessionDate->format('Y-m-d')
                    ]);
                    if ($eval->exists()) {
                        $otsEval = $eval->first();
                        $otsEval->training_record_id = $record->id;
                        $record->ots_eval_id = $otsEval->id;
                        $otsEval->save();
                        $record->save();
                    }
                }
            }
        } catch (Exception $e) {
            return response()->api(generate_error("Unable to save record.", 500));
        }

        return response()->ok();
    }

    /**
     * @SWG\Delete(
     *     path="/training/record/{recordID}",
     *     summary="Delete training record. [Key]",
     *     description="Delete training record. Must have APIKey or be Senior Staff, Training Staff, or the student.",
     *     produces={"application/json"},
     *     tags={"training"},
     *     security={"session", "jwt", "apikey"},
     * @SWG\Response(
     *         response="400",
     *         description="Malformed request.",
     *         @SWG\Schema(ref="#/definitions/error")
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
     *         examples={"application/json":{"status"="OK", "testing": false}}
     *     )
     * )
     *
     * Delete record.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\TrainingRecord      $record
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public
    function deleteRecord(
        Request $request,
        TrainingRecord $record
    ) {
        //Owner instructor and senior staff
        //DELETE /training/record/8

        if ($this->canModify($request, $record)) {
            if (in_array($record->ots_status, [1, 2]) && !RoleHelper::isVATUSAStaff()) {
                return response()->api(generate_error("Unable to delete record because it is an OTS exam. Please contact VATUSA3 or 13 for assistance."),
                    500);
            }
            try {
                $record->delete();
            } catch (Exception $e) {
                return response()->api(generate_error("Unable to delete record."), 500);
            }

            return response()->ok();
        }

        return response()->forbidden();
    }

    /**
     * Determine if requester can modify record
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\TrainingRecord      $record
     *
     * @return bool
     */
    private
    function canModify(
        Request $request,
        TrainingRecord $record
    ): bool {
        $hasApiKey = AuthHelper::validApiKeyv2($request->input('apikey', null), $record->facility->id);
        $isSeniorStaff = Auth::user() && RoleHelper::isSeniorStaff(Auth::user()->cid, $record->facility, true);
        $ownsRecord = $record && Auth::user() && $record->instructor_id == Auth::user()->cid && RoleHelper::isTrainingStaff(Auth::user()->cid,
                true, $record->facility);
        $notOwn = Auth::user() && $record->student_id !== Auth::user()->cid; //No one can modify their own record!

        return ($notOwn && ($isSeniorStaff || $ownsRecord)) || $hasApiKey;
    }

    /**
     * Determine if the request is valid for View routes.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\TrainingRecord|null $record
     * @param \App\User|null           $user
     *
     * @param \App\Facility|null       $facility
     *
     * @return bool
     */
    private
    function canView(
        Request $request,
        TrainingRecord $record = null,
        User $user = null,
        Facility $facility = null
    ): bool {
        $hasApiKey = AuthHelper::validApiKeyv2($request->input('apikey', null),
            $record->facility->id ?? $facility->id ?? $user->facility ?? null);

        //Check Visiting Facilities
        $apiKeyVisitor = false;
        $keyFac = Facility::where("apikey", $request->apikey)
            ->orWhere("api_sandbox_key", $request->apikey)->first();
        if ($request->has('apikey') && $keyFac) {
            if ($record) {
                $apiKeyVisitor = $record->student->visits()->where('facility',
                    $keyFac->id)->exists();
            }
            if ($user) {
                $apiKeyVisitor = $user->visits()->where('facility',
                    $keyFac->id)->exists();
            }
        }
        $visitor = Auth::user() && RoleHelper::isTrainingStaff(Auth::user()->cid,
                true) && ($record && $record->student->visits()->where('facility',
                    Auth::user()->facility)->exists() || $user && $user->visits()->where('facility',
                    Auth::user()->facility)->exists());
        $isTrainingStaff = Auth::user() && RoleHelper::isTrainingStaff(Auth::user()->cid, true,
                $facility ?? Auth::user()->facility) && (!$record || ($record && ($record->student->facility == Auth::user()->facility || $record->facility->id == Auth::user()->facility)));
        $isVATUSAStaff = Auth::user() && RoleHelper::isSeniorStaff(null, null, true);
        $ownsRecord = $record && Auth::user() && $record->student_id === Auth::user()->cid;
        $isOwnUser = Auth::user() && $user && $user->cid === Auth::user()->cid;

        return $hasApiKey || $apiKeyVisitor || $isVATUSAStaff || $visitor || $isTrainingStaff || $ownsRecord || $isOwnUser;
    }

    private
    function canCreate(
        Request $request,
        User $user
    ) {
        $hasApiKey = AuthHelper::validApiKeyv2($request->input('apikey', null), $user->facility);
        
        //Check Visiting Facilities
        $apiKeyVisitor = false;
        $keyFac = Facility::where("apikey", $request->apikey)
            ->orWhere("api_sandbox_key", $request->apikey)->first();
        if ($request->has('apikey') && $keyFac) {
            $apiKeyVisitor = $user->visits()->where('facility',
                $keyFac->id)->exists();
        }
        
        $isTrainingStaff = Auth::user() && RoleHelper::isTrainingStaff(Auth::user()->cid, true, $user->facility);
        if (!$isTrainingStaff && Auth::user()) {
            //Check visiting facilities.
            foreach ($user->visits as $visit) {
                $isTrainingStaff = RoleHelper::isTrainingStaff(Auth::user()->cid, true, $visit->facility);
                if ($isTrainingStaff) {
                    break;
                }
            }
        }
        $notOwn = Auth::user() && $user->cid !== Auth::user()->cid; //No one can add their own record!

        return $notOwn && $isTrainingStaff || $hasApiKey || $apiKeyVisitor;
    }
}
