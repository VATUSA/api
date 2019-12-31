<?php

namespace App\Http\Controllers\API\v2;

use App\Facility;
use App\TrainingRecord;
use App\User;
use Illuminate\Http\Request;

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
    }

    public function getUserRecords(Request $request, User $user)
    {
        //Get records for a User
        // GET /user/1275302/training/records
    }

    public function getFacilityRecords(Request $request, Facility $facility)
    {
        //Get records for a Facility
        // GET /facility/ZSE/training/records
    }

    public function getAllRecords(Request $request)
    {
        //Get all records #nofilter
        // GET /training/records
    }

    public function getOTSEval(Request $request, int $eval)
    {
        //JSON of OTS Evaluation form from ID
        // GET /training/otsEval/8/
    }

    public function getOTSTrainingEval(Request $request, TrainingRecord $record)
    {
        //JSON of OTS Evaluation form from training record
        // GET /training/record/8/otsEval
    }

    public function getOTSEvals(Request $request, User $user)
    {
        //Get OTS Evals for a user.
        //  GET /user/1275302/training/otsEvals
        //Use when selecting which Eval to attach for promotion.
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

    public function editRecord(Request $request, TrainingRecord $record) {
        //Owner instructor and senior staff
        //PUT /training/record/8
    }

    public function deleteRecord(Request $request, TrainingRecord $record) {
    //Owner instructor and senior staff
    //DELETE /training/record/8
}
}
