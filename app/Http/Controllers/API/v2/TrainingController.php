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
 * Note: training records are editable by senior staff and the instructor that created it.
 */
class TrainingController extends Controller
{
    /**
     * Get individual training records.
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
            return $record->toArray();
        }

        return response()->forbidden();

    }

    /**
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
            return TrainingRecord::where('student', $user->cid)->get()->toArray();
        }

        return response()->forbidden();

    }

    /**
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
            return TrainingRecord::where('facility', $facility->id)->get()->toArray();
        }

        return response()->forbidden();

    }

    /**
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
            return TrainingRecord::all()->toArray();
        }

        return response()->forbidden();

    }

    /**
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
            return $eval->getContent();
        }

        return response()->forbidden();

    }

    /**
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
            return $record->otsEval->getContent();
        }

        return response()->forbidden();

    }

    /**
     * Get OTS Evals for a given User.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\User                $user
     *
     * @return array|\Illuminate\Database\Eloquent\Builder
     */
    public function getOTSEvals(Request $request, User $user)
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

            return $return;
        }

        return response()->forbidden();
    }

    /**
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
        //The big one. Submit new record
        // POST /user/1275302/training/record
        // Return training record id... resp()->ok(['id' => 19])

        //Input Data
        $studentId = $request->input("student_id", null);
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

        $duration = Carbon::createFromFormat('H:i', $sessionDate);
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
     * Add OTS Evaluation
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\User                $user
     */
    public
    function postOTSEval(
        Request $request,
        User $user
    ) {
        //Upload OTS Attachment. Required before promotion.
        //Either linked to a training record, or independently created before promotion (trainng_record_id null).
        // Private.
        // POST /user/1275302/training/otsEval
    }

    /**
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
    }

    /**
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
    private
    function canView(
        Request $request,
        TrainingRecord $record = null,
        User $user = null
    ): bool {

        $hasApiKey = AuthHelper::validApiKeyv2($request->input('apikey', null));
        $isTrainingStaff = Auth::user() && RoleHelper::isTrainingStaff();
        $ownsRecord = $record && Auth::user() && $record->student == Auth::user()->cid;
        $isOwnUser = Auth::user() && $user && $user->cid == Auth::user()->cid;

        return $hasApiKey || $isTrainingStaff || $ownsRecord || $isOwnUser;
    }
}
