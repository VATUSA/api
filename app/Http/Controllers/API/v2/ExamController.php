<?php
namespace App\Http\Controllers\API\v2;

use App\Action;
use App\ExamAssignment;
use App\ExamReassignment;
use App\ExamResults;
use App\ExamResultsData;
use App\Helpers\EmailHelper;
use App\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Exam;

class ExamController extends APIController
{

    /**
     * @param Request $request
     * @param $examId
     * @return \Illuminate\Http\JsonResponse
     *
     * @SWG\Post(
     *     path="/exam/queue/{examId}",
     *     summary="Add exam to queue for the VATUSA Exam Center. Requires JWT or Session Cookie",
     *     description="Sets the exam as the queued exam for VEC. Requires JWT or Session Cookie",
     *     produces={"application/json"},
     *     tags={"exam"},
     *     security={"jwt","session"},
     *     @SWG\Parameter(
     *         description="Exam ID to place in queue",
     *         in="path",
     *         name="examId",
     *         required=true,
     *         type="integer",
     *         format="int64"
     *     ),
     *     @SWG\Parameter(description="JWT Token", in="header", name="bearer", required=true, type="string"),
     *     @SWG\Response(
     *         response="404",
     *         description="Exam assignment not found",
     *         @SWG\Schema(
     *             ref="#/definitions/error"
     *         ),
     *         examples={
     *             "application/json":{
     *               "status" = "error",
     *               "message" = "Not Found"
     *             }
     *        },
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Forbidden -- usually the exam assignment doesn't belong to the authenticated user",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","message"="Forbidden"}},
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="Exam has been queued",
     *         @SWG\Schema(ref="#/definitions/OK"),
     *         examples={"application/json":{"status"="OK"}}
     *     )
     * )
     *
     */
    public function postQueue(Request $request, $examId) {
        $ea = ExamAssignment::find($examId);
        if (!$ea) return response()->json(generate_error("Not Found", true), 404);

        if ($ea->cid != \Auth::user()->cid) {
            return response()->json(generate_error("Forbidden", true), 403);
        }

        \Cache::put('exam.queue.' . $ea->cid, $examId, 60);

        return response()->json(['status' => 'OK']);
    }


    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @SWG\Post(
     *     path="/exam/submit",
     *     summary="Submit exam payload for grading. CORS Restricted",
     *     description="Submit exam from VEC for grading. CORS Restricted",
     *     produces={"application/json"},
     *     tags={"exam"},
     *     security={"jwt"},
     *     @SWG\Parameter(description="Exam payload (base64)", in="header", name="payload", required=true, type="string"),
     *     @SWG\Parameter(description="Answers (base64)", in="header", name="answers", required=true, type="string"),
     *     @SWG\Parameter(description="JWT Token", in="header", name="bearer", required=true, type="string"),
     *     @SWG\Response(
     *         response="400",
     *         description="Bad Request, usually for missing parameter",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={{"application/json":{"status"="error","message"="Missing data"}},{"application/json":{"status"="error","message"="Signature doesn't match payload"}}},
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Exam assignment not found",
     *         @SWG\Schema(
     *             ref="#/definitions/error"
     *         ),
     *         examples={
     *             "application/json":{
     *               "status" = "error",
     *               "message" = "Not Found"
     *             }
     *        },
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="Exam has been processed",
     *         @SWG\Schema(
     *             type="object",
     *             @SWG\Property(type="string",property="results"),
     *         ),
     *         examples={{"application/json":{"results"="Not Passed"}},{"application/json":{"results"="Passed"}}},
     *     )
     * )
     */
    public function postSubmit(Request $request) {
        // Make sure all is there
        if (!$request->has('payload') || !$request->has('answers')) {
            return response()->json(generate_error('Missing data', true), 400);
        }

        // Replace ALL spaces with +'s in payload
        $payload = $request->input('payload');
        $payload = str_replace(" ", "+", $payload);

        // Extract payload and verify signature, should prevent tampering
        $payloads = explode(".", $payload);
        if (sha1(env('EXAM_SECRET') . '$' . \Auth::user()->cid . '$' . base64_decode($payloads[0])) != $payloads[1]) {
            return response()->json(generate_error("Signature doesn't match payload", true), 400);
        }

        $answers = json_decode(base64_decode($request->input('answers')), true);
        $questions = json_decode(base64_decode($payloads[0]), true);

        // Verify assignment
        $assign = ExamAssignment::where('cid', \Auth::user()->cid)->where('exam_id', $questions['id'])->first();
        if (!$assign) return response()->json(generate_error("Not found", true), 404);

        $correct = 0;
        $possible = 0;

        $result = new ExamResults();
        $result->exam_id = $questions['id'];
        $result->exam_name = $questions['name'];
        $result->cid = \Auth::user()->cid;
        $result->date = \Carbon\Carbon::now();
        $result->save();

        foreach($questions['questions'] as $question) {
            $possible++;
            $id = $question['id'];
            $erd = new ExamResultsData();
            $erd->result_id = $result->id;
            $erd->question = $question['question'];
            $erd->correct = $question['one'];
            $erd->is_correct = 0;
            if (isset($answers[$id]) && $answers[$id] == "one") {
                $erd->is_correct = 1;
                $erd->selected = $question['one'];
                $correct++;
            }
            else {
                if ($question['type'] == 1) {
                    $erd->selected = ($question['one'] == "True") ? "False" : "True";
                } else {
                    $erd->selected = isset($answers[$id]) ? $question[$answers[$id]] : '';
                }
            }
            $erd->save();
        }

        $exam = Exam::find($questions['id']);

        $score = round(($correct / $possible) * 100);
        $result->score = $score;
        $result->passed = ($score >= $exam->passing_score) ? 1 : 0;
        $result->save();

        // Done... let's send some emails
        $to[] = \Auth::user()->email;
        if ($assign->instructor_id > 111111) {
            $instructor = User::find($assign->instructor_id);
            if ($instructor) $to[] = $instructor->email;
        }
        if ($exam->facility_id != "ZAE") {
            $to[] = $exam->facility_id . "-TA@vatusa.net";
        }

        $log = new Action();
        $log->to = \Auth::user()->cid;
        $log->log = "Exam (" . $exam->facility_id . ") " . $exam->name . " completed.  Score $correct/$possible ($score%).";
        $log->log .= ($exam->passed) ? " Passed." : " Not Passed.";
        $log->save();

        $data = [
            'exam_name' => "(" . $exam->facility_id . ") " . $exam->name,
            'instructor_name' => (isset($instructor)) ? $instructor->fullname() : 'N/A',
            'correct' => $correct,
            'possible' => $possible,
            'score' => $score,
            'student_name' => \Auth::user()->fullname(),
            'reassign' => $exam->retake_period
        ];

        if ($result->passed) {
            $assign->delete();
            $fac = $exam->facility_id;
            if ($fac == "ZAE") { $fac = \Auth::user()->facility; }
            EmailHelper::sendEmailFacilityTemplate($to, "Exam Passed", $fac, "exampassed", $data);
            if ($exam->id == config('exams.BASIC')) {
                \Auth::user()->flag_needbasic = 0;
                \Auth::user()->save();
            }

            return response()->json(['results' => "Passed."]);
        } else {
            if ($exam->retake_period > 0) {
                $reassign = new ExamReassignment();
                $reassign->cid = $assign->cid;
                $reassign->instructor_id = $assign->instructor_id;
                $reassign->exam_id = $assign->exam_id;
                $reassign->reassign_date = \Carbon\Carbon::now()->addDays($exam->retake_period);
                $reassign->save();
            }
            $assign->delete();
            $fac = $exam->facility_id;
            if ($fac == "ZAE") { $fac = \Auth::user()->facility; }
            EmailHelper::sendEmailFacilityTemplate($to, "Exam Not Passed", $fac, "examfailed", $data);

            return response()->json(['results' => "Not Passed."]);
        }
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @SWG\Get(
     *     path="/exam",
     *     summary="Generates and sends exam payload for VATUSA Exam Center based on queued exam for JWT auth'd user. CORS Restricted",
     *     description="Generates and sends exam payload for VATUSA Exam Center based on queued exam for JWT auth'd user. CORS Restricted",
     *     produces={"application/json"},
     *     tags={"exam"},
     *     security={"jwt"},
     *     @SWG\Parameter(description="JWT Token", in="header", name="bearer", required=true, type="string"),
     *     @SWG\Response(
     *         response="404",
     *         description="Queue/Exam Assignment not found",
     *         @SWG\Schema(
     *             ref="#/definitions/error"
     *         ),
     *         examples={
     *             {"application/json":{"status"="error","message"="No exam queued"}},
     *             {"application/json":{"status"="error","message"="No matching exam assignment"}},
     *        },
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="Exam generated",
     *         @SWG\Schema(type="object", @SWG\Property(property="payload", type="string", description="base64 encoded quiz payload, with signature appended"))
     *     )
     * )
     */
    public function getRequest(Request $request) {
        if (!\Cache::has('exam.queue.' . \Auth::user()->cid)) {
            return response()->json(generate_error("No exam queued", true), 404);
        }
        $assign = ExamAssignment::find(\Cache::get('exam.queue.' . \Auth::user()->cid));
        if (!$assign) {
            return response()->json(generate_error("No matching exam assignment", true), 404);
        }
        $exam = Exam::find($assign->exam_id);

        // @TODO if (!ExamHelper::examCBTComplete($exam))

        if ($exam->number > 0)
            $questions = $exam->questions()->orderBy(\DB::raw('RAND()'))->take($exam->number)->get();
        else
            $questions = $exam->questions()->orderBy(\DB::raw('RAND()'))->get();

        $json = [
            'id' => $exam->id,
            'name' => $exam->name,
        ];
        $x = 0;
        foreach ($questions as $question) {
            $questiontemp = [
                'id' => $question->id,
                'question' => preg_replace("/\r?\n/", '<br>', $question->question),
                'illustration' => $question->illustration,
                'type' => $question->type
            ];
            if ($question->type == 0) {
                $order = ['one','two','three','four']; shuffle($order);
                $questiontemp['one'] = preg_replace("/\r?\n/", '<br>', $question->answer);
                $questiontemp['two'] = preg_replace("/\r?\n/", '<br>', $question->alt1);
                $questiontemp['three'] = preg_replace("/\r?\n/", '<br>', $question->alt2);
                $questiontemp['four'] = preg_replace("/\r?\n/", '<br>', $question->alt3);
                $questiontemp['order'] = $order;
            } else {
                $questiontemp['one'] = $question->answer;
            }

            $json['questions'][] = $questiontemp;
            $x++;
        }
        $json['numQuestions'] = $x;
        $json = json_encode($json, JSON_HEX_APOS | JSON_NUMERIC_CHECK);
        $sig = sha1(env('EXAM_SECRET') . '$' . \Auth::user()->cid . '$' . $json);
        return response()->json([
            'payload' => base64_encode($json) . "." . $sig
        ]);
    }
}
