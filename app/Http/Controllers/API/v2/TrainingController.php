<?php

namespace App\Http\Controllers\API\v2;

use App\Http\Controllers\Controller;
use App\Facility;
use App\Helpers\AuthHelper;
use App\Helpers\RoleHelper;
use App\OTSEval;
use App\Rating;
use App\Role;
use App\TrainingRecord;
use App\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Mews\Purifier\Facades\Purifier;

/**
 * Class TrainingController
 * @package App\Http\Controllers\API\v2
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
            return response()->api($record->toArray());
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
            return response()->api(TrainingRecord::where('student_id', $user->cid)->get()->toArray());
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
    public function getFacilityRecords(Request $request, Facility $facility)
    {
        //Get records for a Facility
        // GET /facility/ZSE/training/records
        if ($this->canView($request)) {
            return response()->api(TrainingRecord::where('facility', $facility->id)->get()->toArray());
        }

        return response()->forbidden();

    }

    /**
     * @SWG\Get(
     *     path="/training/records",
     *     summary="Get all training records. [Key]",
     *     description="Get all training records. Must have APIKey or be Senior Staff or Training Staff.",
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
    public function getAllRecords(Request $request)
    {
        //Get all records #nofilter
        // GET /training/records
        if ($this->canView($request)) {
            return response()->api(TrainingRecord::all()->toArray());
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
    public function getOTSEval(Request $request, OTSEval $eval)
    {
        //JSON of OTS Evaluation form from ID
        // GET /training/otsEval/8/
        if ($this->canView($request)) {
            return response()->api(['content' => $eval->getContent()]);
        }

        return response()->forbidden();

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
    public function getOTSTrainingEval(Request $request, TrainingRecord $record)
    {
        //JSON of OTS Evaluation form from training record
        // GET /training/record/8/otsEval
        if ($this->canView($request)) {
            return response()->api(['content' => $record->otsEval->getContent()]);
        }

        return response()->forbidden();

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
    public function getUserOTSEvals(Request $request, User $user)
    {
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
    public function getOTSEvals(Request $request)
    {
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
     * @SWG\Parameter(name="num_movements", in="formData", type="integer", required=false, description="Number of
                                            Movements"),
     * @SWG\Parameter(name="score", in="formData", type="integer", required=false, description="Session Score, 1-5"),
     * @SWG\Parameter(name="notes", in="formData", type="string", required=true, description="Session Notes"),
     * @SWG\Parameter(name="location", in="formData", type="integer", required=true, description="Session Location (0 =
                                       Classroom, 1 = Live, 2 = Sweatbox)"),
     * @SWG\Parameter(name="is_ots", in="formData", type="boolean", required=false, description="Session is OTS
                                     Attempt"),
     * @SWG\Parameter(name="is_cbt", in="formData", type="boolean", required=false, description="Record is a CBT
                                     Completion"),
     * @SWG\Parameter(name="solo_granted", in="formData", type="boolean", required=false, description="Solo endorsement
                                           was granted"),
     * @SWG\Parameter(name="ots_result", in="formData", type="boolean", required=false, description="OTS Result: true =
                                         pass."),
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
    public function postNewRecord(Request $request, User $user)
    {
        //Submit new record
        // POST /user/1275302/training/record
        // Return training record id... resp()->ok(['id' => 19])

        //Input Data
        $studentId = $user->cid;
        $instructorId = $request->input("instructor_id", null);
        $sessionDate = $request->input("session_date", null);
        $position = $request->input("position", null);
        $duration = $request->input("duration", null);
        $numMovements = $request->input("num_movements", null);
        $score = $request->input("score", null);
        $notes = $request->input("notes", null);
        $location = $request->input("location", null);
        $isOTS = $request->input("is_ots", false);
        $isCBT = $request->input("is_cbt", false);
        $soloGranted = $request->input("solo_granted", false);
        $otsResult = $request->input("ots_result", false);

        if (Auth::check()) {
            //Authenticated
            $facility = Auth::user()->facility;
        } else {
            //Use API key.
            $facility = Facility::where('apikey', $request->apikey)
                ->orWhere('api_sandbox_key', $request->apikey)->first()->id;
        }

        //Validate
        if (!$studentId || (!$instructorId && !Auth::check()) || !$sessionDate || !$position || !$notes) {
            //Required Fields
            return response()->api(generate_error("Missing fields; see API documentation."), 400);
        }
        if ($numMovements && !is_numeric($numMovements)) {
            return response()->api(generate_error("Invalid number of movements, must be null or an integer."), 400);
        }
        if ($score && !is_numeric($score)) {
            return response()->api(generate_error("Invalid score, must be null or an integer."), 400);
        }
        if (!preg_match("/^([A-Z0-9]{2,3})_(TWR|APP|CTR)$/", $position)) {
            return response()->api(generate_error("Invalid position."), 400);
        }
        if (!in_array(intval($location), [0, 1, 2])) {
            return response()->api(generate_error("Invalid session location. Must be 0, 1, or 2."), 400);
        }

        $sessionDate = Carbon::createFromFormat("Y-m-d H:i", $sessionDate);
        if (!$sessionDate) {
            return response()->api(generate_error("Invalid date; must be YY-mm-dd HH:MM."), 400);
        }

        $duration = Carbon::createFromFormat('H:i', $duration);
        if (!$duration) {
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
        $record->num_movements = $numMovements;
        $record->score = $score;
        $record->notes = $notes;
        $record->location = $location;
        $record->is_ots = $isOTS;
        $record->is_cbt = $isCBT;
        $record->solo_granted = $soloGranted;
        $record->ots_result = $otsResult;

        try {
            $record->saveOrFail();
        } catch (Exception $e) {
            return response()->api(generate_error("Unable to save record.", 500));
        }

        return response()->ok(['id' => $record->id]);
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
     */
    public function postOTSEval(Request $request, User $user)
    {
        //Upload OTS Attachment. Required before promotion.
        //Either linked to a training record, or independently created before promotion (trainng_record_id null).
        // Private.
        // POST /user/1275302/training/otsEval
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
     * @SWG\Parameter(name="student_id", in="formData", type="integer", description="Student CID"),
     * @SWG\Parameter(name="instructor_id", in="formData", type="integer", description="Instructor CID"),
     * @SWG\Parameter(name="session_date", in="formData", type="string", description="Session Date, YY-mm-dd HH:mm"),
     * @SWG\Parameter(name="position", in="formData", type="string", description="Position ID
    (XYZ_APP, ZZZ_CTR)"),
     * @SWG\Parameter(name="duration", in="formData", type="string", description="Session Dueation, HH:mm"),
     * @SWG\Parameter(name="num_movements", in="formData", type="integer", description="Number of Movements"),
     * @SWG\Parameter(name="score", in="formData", type="integer", description="Session Score, 1-5"),
     * @SWG\Parameter(name="notes", in="formData", type="string", description="Session Notes"),
     * @SWG\Parameter(name="location", in="formData", type="integer", description="Session Location (0 = Classroom, 1 =
                                       Live, 2 = Sweatbox)"),
     * @SWG\Parameter(name="is_ots", in="formData", type="boolean", description="Session is OTS Attempt"),
     * @SWG\Parameter(name="is_cbt", in="formData", type="boolean", description="Record is a CBT Completion"),
     * @SWG\Parameter(name="solo_granted", in="formData", type="boolean", description="Solo endorsement was granted"),
     * @SWG\Parameter(name="ots_result", in="formData", type="boolean", description="OTS Result: true = pass."),
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
    public function editRecord(Request $request, TrainingRecord $record)
    {
        //Owner instructor and senior staff
        //PUT /training/record/8
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
    public function deleteRecord(Request $request, TrainingRecord $record)
    {
        //Owner instructor and senior staff
        //DELETE /training/record/8

        if ($this->canModify($request, $record)) {
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
    private function canModify(Request $request, TrainingRecord $record): bool
    {
        $hasApiKey = AuthHelper::validApiKeyv2($request->input('apikey', null));
        $isSeniorStaff = Auth::user() && RoleHelper::isSeniorStaff(Auth::user()->cid, Auth::user()->facility, true);
        $ownsRecord = $record && Auth::user() && $record->instructor_id == Auth::user()->cid;

        return $hasApiKey || $isSeniorStaff || $ownsRecord;
    }

    /**
     * Determine if the request is valid for View routes.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\TrainingRecord|null $record
     * @param \App\User|null           $user
     *
     * @return bool
     */
    private function canView(Request $request, TrainingRecord $record = null, User $user = null): bool
    {

        $hasApiKey = AuthHelper::validApiKeyv2($request->input('apikey', null));
        $isTrainingStaff = Auth::user() && RoleHelper::isTrainingStaff();
        $ownsRecord = $record && Auth::user() && $record->student == Auth::user()->cid;
        $isOwnUser = Auth::user() && $user && $user->cid == Auth::user()->cid;

        return $hasApiKey || $isTrainingStaff || $ownsRecord || $isOwnUser;
    }
}
