<?php

namespace App\Http\Controllers\API\v2;

use App\Helpers\AuthHelper;
use App\Helpers\EmailHelper;
use App\Helpers\RatingHelper;
use App\Helpers\RoleHelper;
use App\Role;
use App\Transfer;
use App\User;
use App\Exam;
use App\ExamResults;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Facility;

/**
 * Class StatsController
 * @package App\Http\Controllers\API\v2
 */
class StatsController  extends APIController
{
    /**
     * @return string
     *
     * @TODO
     *
     * @SWG\Get(
     *     path="/stats/exams/(facility)",
     *     summary="Get statistics of exam results",
     *     description="Get statistics of exam results",
     *     produces={"application/json"},
     *     tags={"stats"},
     *     @SWG\Parameter(name="facility", in="path", type="string", description="Filter for facility IATA ID"),
     *     @SWG\Parameter(name="month", in="query", type="integer", description="Filter by month number, requires year"),
     *     @SWG\Parameter(name="year", in="query", type="integer", description="4 digit year to limit results by"),
     *     @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(
     *             type="array",
     *             @SWG\Items(
     *                 type="object",
     *                 @SWG\Property(property="id", type="integer", description="Solo Certification id"),
     *                 @SWG\Property(property="cid",type="integer",description="CERT ID of user"),
     *                 @SWG\Property(property="lastname",type="string",description="Last name"),
     *                 @SWG\Property(property="firstname",type="string",description="First name"),
     *                 @SWG\Property(property="position", type="string", description="Position ID (XYZ_APP, ZZZ_CTR)"),
     *                 @SWG\Property(property="expDate", type="string", description="Expiration Date (YYYY-MM-DD)"),
     *             ),
     *         ),
     *     )
     * ),
     */
    public function getExams(Request $request, $facility = null) {
        if ($request->has("month") && !$request->has("year")) {
            return response()->api(generate_error("Missing required field", true), 400);
        }
        $facility = Facility::find($facility);
        if (!$facility || ($facility->active != 1 && !in_array($facility->id, ['ZAE','ZHQ']))) {
            return response()->api(generate_error("Facility not found"), 404);
        }

        $results = [];
        foreach (Exam::where('facility_id', $facility->id)->orderBy('name')->get() as $exam) {
            $result = ['id' => $exam->id, 'name' => $exam->name, 'taken' => 0, 'passed' => 0, 'failed' => 0];

            $examresults = ExamResults::where('exam_id', $exam->id);
            if ($request->has("month")) {
                $examresults = $examresults->where("date", "LIKE", $request->input("year") . "-" . sprintf("%02d", $request->input("month")) . '%');
            } elseif ($request->has("year")) {
                $examresults = $examresults->where("date", "LIKE", $request->input("year") . "-" . '%');
            }
            $examresults = $examresults->get();
            foreach($examresults as $examresult) {
                $result['taken']++;
                if ($examresult->passed == 1) { $result['passed']++; }
                else { $result['failed']++; }
            }

            $results[] = $result;
        }

        return response()->api($results);
    }
}
