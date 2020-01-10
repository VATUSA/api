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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Class TrainingController
 * @package App\Http\Controllers\API\v2
 * Note: training records are editable by senior staff and the instructor that created it.
 */
class TrainingController extends Controller
{
    public function getTrainingRecord(Request $request, TrainingRecord $record)
    {
        //Get training record info
        // GET /training/record/8
        if ($this->canView($request, $record)) {
            return $record->toArray();
        }

        return response()->forbidden();

    }

    public function getUserRecords(Request $request, User $user)
    {
        //Get records for a User
        // GET /user/1275302/training/records
        if ($this->canView($request, null, $user)) {
            return TrainingRecord::where('student', $user->cid)->get()->toArray();
        }

        return response()->forbidden();

    }

    public function getFacilityRecords(Request $request, Facility $facility)
    {
        //Get records for a Facility
        // GET /facility/ZSE/training/records
        if ($this->canView($request)) {
            return TrainingRecord::where('facility', $facility->id)->get()->toArray();
        }

        return response()->forbidden();

    }

    public function getAllRecords(Request $request)
    {
        //Get all records #nofilter
        // GET /training/records
        if ($this->canView($request)) {
            return TrainingRecord::all()->toArray();
        }

        return response()->forbidden();

    }

    public function getOTSEval(Request $request, OTSEval $eval)
    {
        //JSON of OTS Evaluation form from ID
        // GET /training/otsEval/8/
        if ($this->canView($request)) {
            return $eval->getContent();
        }

        return response()->forbidden();

    }

    public function getOTSTrainingEval(Request $request, TrainingRecord $record)
    {
        //JSON of OTS Evaluation form from training record
        // GET /training/record/8/otsEval
        if ($this->canView($request)) {
            return $record->otsEval->getContent();
        }

        return response()->forbidden();

    }

    public function getOTSEvals(Request $request, User $user)
    {
        //Get OTS Evals for a user.
        //  GET /user/1275302/training/otsEvals
        // QSP rating_id Filter by rating
        //Use when selecting which Eval to attach for promotion.
        $rating = $request->input('rating_id', null); //Rating
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

    public function postNewRecord(Request $request, User $user)
    {
        //The big one. Submit new record
        // POST /user/1275302/training/record
    }

    public function postOTSEval(Request $request, User $user)
    {
        //Upload OTS Attachment. Required before promotion.
        //Either linked to a training record, or independently created before promotion (trainng_record_id null).
        // POST /user/1275302/training/otsEval
        //TODO Link to Gist of correct OTS Eval format
    }

    public function editRecord(Request $request, TrainingRecord $record)
    {
        //Owner instructor and senior staff
        //PUT /training/record/8
    }

    public function deleteRecord(Request $request, TrainingRecord $record)
    {
        //Owner instructor and senior staff
        //DELETE /training/record/8
    }

    private function canModify(TrainingRecord $record): bool
    {
        //
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
