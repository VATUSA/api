<?php

namespace App\Http\Controllers\API\v2;

use App\Action;
use App\ExamAssignment;
use App\ExamQuestions;
use App\ExamReassignment;
use App\ExamResults;
use App\ExamResultsData;
use App\Helpers\EmailHelper;
use App\Helpers\RoleHelper;
use App\Helpers\AuthHelper;
use App\TrainingBlock;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Exam;

class ExamController extends APIController
{

    /**
     * @param Request $request
     * @param         $examId
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @SWG\Post(
     *     path="/exam/queue/{examId}",
     *     summary="Add exam to queue for the VATUSA Exam Center. [Private]",
     *     description="Sets the exam as the queued exam for VEC. CORS Restricted.",
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
     *         examples={"application/json":{"status"="OK","testing"=false}}
     *     )
     * )
     *
     */
    public function postQueue(Request $request, $examId)
    {
        $ea = ExamAssignment::find($examId);
        if (!$ea) {
            return response()->api(generate_error("Not Found", true), 404);
        }

        if ($ea->cid != \Auth::user()->cid) {
            return response()->api(generate_error("Forbidden", true), 403);
        }

        if (!$ea->exam->CBTComplete(\Auth::user())) {
            return response()->api([
                "msg"         => "CBTs are not complete",
                "cbt"         => $ea->exam->CBT->name,
                "cbtFacility" => $ea->exam->CBT->facility
            ], 400);
        }

        if (!isTest()) {
            \Cache::put('exam.queue.' . $ea->cid, $examId, 60);
        }

        return response()->ok();
    }


    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @SWG\Post(
     *     path="/exam/submit",
     *     summary="Submit exam payload for grading. [Private]",
     *     description="Submit exam from VEC for grading. CORS Restricted",
     *     produces={"application/json"},
     *     tags={"exam"},
     *     security={"jwt"},
     *     @SWG\Parameter(description="Exam payload (base64)", in="header", name="payload", required=true,
     *                                      type="string"),
     *     @SWG\Parameter(description="Answers (base64)", in="header", name="answers", required=true, type="string"),
     *     @SWG\Parameter(description="JWT Token", in="header", name="bearer", required=true, type="string"),
     *     @SWG\Response(
     *         response="400",
     *         description="Bad Request, usually for missing parameter",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={{"application/json":{"status"="error","message"="Missing
     *         data"}},{"application/json":{"status"="error","message"="Signature doesn't match payload"}}},
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
     * @throws \Exception
     */
    public function postSubmit(Request $request)
    {
        // Make sure all is there
        if (!$request->has('payload') || !$request->has('answers')) {
            return response()->api(generate_error('Missing data', true), 400);
        }

        // Replace ALL spaces with +'s in payload
        $payload = $request->input('payload');
        $payload = str_replace(" ", "+", $payload);

        // Extract payload and verify signature, should prevent tampering
        $payloads = explode(".", $payload);
        if (hash('sha256',
                env('EXAM_SECRET') . '$' . \Auth::user()->cid . '$' . base64_decode($payloads[0])) != $payloads[1]) {
            return response()->api(generate_error("Signature doesn't match payload", true), 400);
        }

        $answers = json_decode(base64_decode($request->input('answers')), true);
        $questions = json_decode(base64_decode($payloads[0]), true);

        // Verify assignment
        $assign = ExamAssignment::where('cid', \Auth::user()->cid)->where('exam_id', $questions['id'])->first();
        if (!$assign) {
            return response()->api(generate_error("Not found", true), 404);
        }

        $correct = 0;
        $possible = 0;

        $result = new ExamResults();
        $result->exam_id = $questions['id'];
        $result->exam_name = $questions['name'];
        $result->cid = \Auth::user()->cid;
        $result->date = \Carbon\Carbon::now();
        $result->save();

        foreach ($questions['questions'] as $question) {
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
            } else {
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
            if ($instructor) {
                $to[] = $instructor->email;
            }
        }
        if ($exam->facility_id != "ZAE") {
            $to[] = $exam->facility_id . "-TA@vatusa.net";
        }

        $log = new Action();
        $log->to = \Auth::user()->cid;
        $log->log = "Exam (" . $exam->facility_id . ") " . $exam->name . " completed.  Score $correct/$possible ($score%).";
        $log->log .= ($result->passed) ? " Passed." : " Not Passed.";
        $log->save();

        $data = [
            'exam_name'       => "(" . $exam->facility_id . ") " . $exam->name,
            'instructor_name' => (isset($instructor)) ? $instructor->fullname() : 'N/A',
            'correct'         => $correct,
            'possible'        => $possible,
            'score'           => $score,
            'student_name'    => \Auth::user()->fullname(),
            'reassign'        => 0,
            'reassign_date'   => null
        ];

        if ($result->passed) {
            $assign->delete();
            $fac = $exam->facility_id;
            if ($fac == "ZAE") {
                $fac = \Auth::user()->facility;
            }
            EmailHelper::sendEmailFacilityTemplate($to, "Exam Passed", $fac, "exampassed", $data);
            if ($exam->id == config('exams.BASIC')) {
                \Auth::user()->flag_needbasic = 0;
                \Auth::user()->save();
            }

            return response()->api(['results' => "Passed."]);
        } else {
            if ($exam->retake_period > 0) {
                $reassign = new ExamReassignment();
                $reassign->cid = $assign->cid;
                $reassign->instructor_id = $assign->instructor_id;
                $reassign->exam_id = $assign->exam_id;
                $reassign->reassign_date = \Carbon\Carbon::now()->addDays($exam->retake_period);
                $reassign->save();

                $data['reassign'] = $exam->retake_period;
                $data['reassign_date'] = $reassign->reassign_date;
            }
            $assign->delete();
            $fac = $exam->facility_id;
            if ($fac == "ZAE") {
                $fac = \Auth::user()->facility;
            }
            EmailHelper::sendEmailFacilityTemplate($to, "Exam Not Passed", $fac, "examfailed", $data);

            return response()->api(['results' => "Not Passed."]);
        }
    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @SWG\Get(
     *     path="/exam/request",
     *     summary="Generates and sends exam payload for VATUSA Exam Center based on queued exam for JWT auth'd user.
    [Private]",
     *     description="Generates and sends exam payload for VATUSA Exam Center based on queued exam for
    JWT auth'd user. CORS Restricted",
     *     produces={"application/json"}, tags={"exam"}, security={"jwt"},
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
     *         @SWG\Schema(type="object", @SWG\Property(property="payload", type="string", description="base64 encoded
    quiz payload, with signature appended"))
     *     )
     * )
     */
    public function getRequest(Request $request)
    {
        if (!\Cache::has('exam.queue.' . \Auth::user()->cid)) {
            return response()->api(generate_error("No exam queued", true), 404);
        }
        $assign = ExamAssignment::find(\Cache::get('exam.queue.' . \Auth::user()->cid));
        if (!$assign) {
            return response()->api(generate_error("No matching exam assignment", true), 404);
        }
        $exam = Exam::find($assign->exam_id);

        if (!$exam->CBTComplete(\Auth::user())) {
            return response()->api([
                "msg"         => "CBTs are not complete",
                "cbt"         => $exam->CBT->name,
                "cbtFacility" => $exam->CBT->facility
            ], 400);
        }

        if ($exam->number > 0) {
            $questions = $exam->questions()->orderBy(\DB::raw('RAND()'))->take($exam->number)->get();
        } else {
            $questions = $exam->questions()->orderBy(\DB::raw('RAND()'))->get();
        }

        $json = [
            'id'   => $exam->id,
            'name' => $exam->name,
        ];
        $x = 0;
        foreach ($questions as $question) {
            $questiontemp = [
                'id'           => $question->id,
                'question'     => preg_replace("/\r?\n/", '<br>', $question->question),
                'illustration' => $question->illustration,
                'type'         => $question->type
            ];
            if ($question->type == 0) {
                $order = ['one', 'two', 'three', 'four'];
                shuffle($order);
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
        $sig = hash('sha256', env('EXAM_SECRET') . '$' . \Auth::user()->cid . '$' . $json);

        return response()->api([
            'payload' => base64_encode($json) . "." . $sig
        ]);
    }

    /**
     *
     * @SWG\Get(
     *     path="/exams/{facility}",
     *     summary="Get list of exams",
     *     description="Generates list of exams.",
     *     produces={"application/json"},
     *     tags={"exam"},
     *     @SWG\Parameter(name="facility", in="path", type="string", description="(OPTIONAL) Filter list by Facility
    IATA ID"),
     *     @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(
     *             type="array",
     *             @SWG\Items(
     *                 ref="#/definitions/Exam"
     *             ),
     *         ),
     *         examples={"application/json":{{"id":50,"facility_id":"ZAE","name":"VATUSA - S2 Rating (TWR) Controller
               Exam","number":20,"is_active":1,"cbt_required":118,"retake_period":3,"passing_score":80,"answer_visibility":"all_passed"}}},
     *     )
     * )
     * @param \Illuminate\Http\Request $request
     * @param null                     $facility
     *
     * @return \Illuminate\Http\Response
     */
    public function getExams(Request $request, $facility = null)
    {
        if ($facility) {
            $exams = Exam::where('facility_id', $facility);
        } else {
            $exams = Exam::where('facility_id', 'LIKE', '%');
        }
        $exams = $exams->orderBy('name')->get();

        return response()->api($exams->toArray());
    }

    /**
     *
     * @SWG\Get(
     *     path="/exams/{examid}",
     *     summary="Get exam details",
     *     description="Get exam details by ID",
     *     produces={"application/json"},
     *     tags={"exam"},
     *     @SWG\Parameter(name="examid", in="path", type="string", required=true, description="Get exam details of
     *                                   id"),
     *     @SWG\Response(
     *         response="404",
     *         description="Facility/Exam Not found",
     *         @SWG\Schema(
     *             ref="#/definitions/error"
     *         ),
     *         examples={"application/json":{"status"="error","message"="Not Found"}},
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(
     *             ref="#/definitions/Exam"
     *         ),
     *         examples={"application/json":{"id":50,"facility_id":"ZAE","name":"VATUSA - S2 Rating (TWR) Controller
               Exam","number":20,"is_active":1,"cbt_required":118,"retake_period":3,"passing_score":80,"answer_visibility":"all_passed"}},
     *     )
     * )
     * @param \Illuminate\Http\Request $request
     * @param                          $id
     *
     * @return \Illuminate\Http\Response
     */
    public function getExambyId(Request $request, $id)
    {
        $exam = Exam::find($id);
        if (!$exam) {
            return response()->api(generate_error("Not found"), 404);
        }

        return response()->api($exam->toArray());
    }

    /**
     *
     * @SWG\Get(
     *     path="/exams/{examid}/questions",
     *     summary="Generate list of questions. [Auth]",
     *     description="Generates list of questions. Session cookie or JWT required.",
     *     produces={"application/json"},
     *     tags={"exam"},
     *     security={"session","jwt"},
     *     @SWG\Parameter(name="examid", in="path", type="string", required=true, description="exam id"),
     *     @SWG\Response(
     *         response="404",
     *         description="Not found",
     *         @SWG\Schema(
     *             ref="#/definitions/error"
     *         ),
     *         examples={"application/json":{"status"="error","message"="Not Found"}},
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(
     *             ref="#/definitions/Exam"
     *         ),
     *         examples={"application/json":{{"id":1959,"exam_id":53,"question":"Green Bay is a Class __
               airspace.","type":0,"answer":"Charlie","alt1":"Bravo","alt2":"Delta","alt3":"Foxtrot"}}},
     *     )
     * )
     *
     * @param \Illuminate\Http\Request $request
     * @param                          $id
     *
     * @return \Illuminate\Http\Response
     */
    public function getExamQuestions(Request $request, $id)
    {
        $exam = Exam::find($id);
        if (!$exam) {
            return response()->api(generate_error("Not found"), 404);
        }

        $result = $exam->questions->toArray();

        if (ExamAssignment::hasAssignment(\Auth::user()->cid, $id)) {
            $result['answer'] = null;
            $result['alt1'] = null;
            $result['alt2'] = null;
            $result['alt3'] = null;
        }

        return response()->api($exam->questions->toArray());
    }

    /**
     *
     * @SWG\Put(
     *     path="/exams/{examid}",
     *     summary="Edit details of exam. [Private]",
     *     description="Edit details of exam. CORS Restricted",
     *     produces={"application/json"},
     *     tags={"exam"},
     *     @SWG\Parameter(name="facility", in="path", type="string", required=true, description="Filter list by
    Facility IATA ID"),
     *     @SWG\Parameter(name="examid", in="path", type="integer", required=true, description="Exam ID"),
     *     @SWG\Parameter(name="name", in="formData", type="string", description="Exam name"),
     *     @SWG\Parameter(name="cbtRequired", in="formData", type="integer", description="ID of CBT Required"),
     *     @SWG\Parameter(name="passingScore", in="formData", type="integer", description="Passing Score Percentage *
    100"),
     *     @SWG\Parameter(name="retakePeriod", in="formData", type="integer", description="Auto reassign on fail after
    X days, 0 = no auto reassign, valid values: 1, 3, 5, 7, 14"),
     *     @SWG\Parameter(name="numberQuestions", in="formData", type="integer", description="Number of questions to
    ask, 0 = all"),
     *     @SWG\Parameter(name="active", in="formData", type="integer", description="Is exam active? (numeric
    representation of bool 1 = active, 0 = not active)"),
     *     @SWG\Response(
     *         response="401",
     *         description="Unauthorized",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Unauthorized"}},
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Forbidden -- needs to have role of ATM, DATM or VATUSA Division staff member",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","message"="Forbidden"}},
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Facility/Exam Not found",
     *         @SWG\Schema(
     *             ref="#/definitions/error"
     *         ),
     *         examples={"application/json":{"status"="error","message"="Not Found"}},
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(
     *             ref="#/definitions/OK",
     *         )
     *     )
     * )
     * @param \Illuminate\Http\Request $request
     * @param string                   $id
     *
     * @return \Illuminate\Http\Response
     */
    public function putExam(Request $request, string $id)
    {
        if (!\Auth::check()) {
            return response()->api(generate_error("Unauthorized"), 401);
        }
        $return = [];
        $error = [];
        $exam = Exam::find($id);
        if (!$exam) {
            return response()->api(generate_error("Not found"), 404);
        }
        if (!RoleHelper::isSeniorStaff(\Auth::user()->cid, $exam->facility_id, true) && !RoleHelper::isVATUSAStaff()) {
            return response()->api(generate_error("Forbidden"), 403);
        }

        if ($request->has("cbtRequired") && is_numeric($request->input("cbtRequired"))) {
            $exam->cbt_required = $request->input("cbtRequired");
        } elseif ($request->has("cbtRequired")) {
            $error[] = "CBT Required ID not valid";
        }

        if ($request->has("passingScore") && is_numeric($request->input("passingScore"))) {
            if ($request->input("passingScore") < 2 || $request->input("passingScore") > 100) {
                $exam->passing_score = $request->input("passingScore");
            } else {
                $error[] = "Passing Score not valid";
            }
        }

        if ($request->has("retakePeriod")) {
            if (is_numeric($request->input("retakePeriod"))) {
                $exam->retake_period = $request->input("retakePeriod");
            } else {
                $error[] = "Invalid retake period";
            }
        }

        if ($request->has("numberQuestions")) {
            if (is_numeric($request->input("numberQuestions"))) {
                $exam->number_questions = $request->input("numberQuestions");
            } else {
                $error[] = "Invalid number questions";
            }
        }

        if ($request->has("active")) {
            if ($request->input("active") > 1 || $request->input("active") < 0) {
                $error[] = "Invalid active value";
            } else {
                $exam->active = $request->input("active");
            }
        }

        $exam->save();

        $return['status'] = "ok";
        if (!empty($error)) {
            $return['errors'] = $error;
        }

        return response()->api(['status' => 'ok', 'errors' => $error]);
    }

    /**
     *
     * @SWG\Post(
     *     path="/exams/{examid}",
     *     summary="Create new question. [Private]",
     *     description="Create new question. CORS Restricted.",
     *     produces={"application/json"},
     *     tags={"exam"},
     *     @SWG\Parameter(name="facility", in="path", type="string", required=true, description="Filter list by
    Facility IATA ID"),
     *     @SWG\Parameter(name="examid", in="path", type="integer", required=true, description="Exam ID"),
     *     @SWG\Parameter(name="question", in="formData", type="string", required=true, description="Question text"),
     *     @SWG\Parameter(name="type", in="formData", type="string", required=true, description="Type of question
    (multiple|truefalse)"),
     *     @SWG\Parameter(name="choice1", in="formData", type="string", required=true, description="Answer"),
     *     @SWG\Parameter(name="choice2", in="formData", type="string", description="Distractor #1 (only for
    type=multiple)"),
     *     @SWG\Parameter(name="choice3", in="formData", type="string", description="Distractor #2 (only for
    type=multiple)"),
     *     @SWG\Parameter(name="choice4", in="formData", type="string", description="Distractor #3 (only for
    type=multiple)"),
     *     @SWG\Response(
     *         response="401",
     *         description="Unauthorized",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Unauthorized"}},
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Forbidden -- needs to have role of ATM, DATM or VATUSA Division staff member",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","message"="Forbidden"}},
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(
     *             ref="#/definitions/OKID"
     *         ),
     *     )
     * )
     * @param \Illuminate\Http\Request $request
     * @param                          $examid
     *
     * @return \Illuminate\Http\Response
     */
    public function postExamQuestion(Request $request, $examid)
    {
        if (!\Auth::check()) {
            return response()->api(generate_error("Unauthorized"), 401);
        }
        $exam = Exam::find($examid);
        if (!$exam) {
            return response()->api(generate_error("Not found"), 404);
        }
        if (!RoleHelper::isSeniorStaff(\Auth::user()->cid, $exam->facility_id, true) && !RoleHelper::isVATUSAStaff()) {
            return response()->api(generate_error("Forbidden"), 403);
        }

        $question = new ExamQuestions();
        $question->exam_id = $examid;
        $question->question = $request->input("question");
        if ($request->input("type") == "multiple") {
            $question->type = 1;
            $question->answer = $request->input("choice1");
            $question->alt1 = $request->input("choice2");
            $question->alt3 = $request->input("choice3");
            $question->alt4 = $request->input("choice4");
        } elseif ($request->input("type") == "truefalse") {
            if ($request->input("choice1") != "True" && $request->input("choice1") != "False") {
                return response()->api(generate_error("Invalid answer"), 400);
            }
            $question->type = 0;
            $question->answer = $request->input("choice1");
        }
        $question->save();

        return response()->api(["status" => "OK", "id" => $question->id]);
    }

    /**
     *
     * @SWG\Put(
     *     path="/exams/{examid}/{questionID}",
     *     summary="Edit question. [Private]",
     *     description="Edit question. CORS Restricted.",
     *     produces={"application/json"},
     *     tags={"exam"},
     *     @SWG\Parameter(name="facility", in="path", type="string", required=true, description="Filter list by
     *                                     Facility IATA ID"),
     *     @SWG\Parameter(name="examid", in="path", type="integer", required=true, description="Exam ID"),
     *     @SWG\Parameter(name="questionid", in="path", type="integer", required=true, description="Question ID"),
     *     @SWG\Parameter(name="question", in="formData", type="string", required=true, description="Question text"),
     *     @SWG\Parameter(name="type", in="formData", type="string", required=true, description="Type of question
    (multiple|truefalse)"),
     *     @SWG\Parameter(name="choice1", in="formData", type="string", required=true, description="Answer"),
     *     @SWG\Parameter(name="choice2", in="formData", type="string", description="Distractor #1 (only for
    type=multiple)"),
     *     @SWG\Parameter(name="choice3", in="formData", type="string", description="Distractor #2 (only for
    type=multiple)"),
     *     @SWG\Parameter(name="choice4", in="formData", type="string", description="Distractor #3 (only for
    type=multiple)"),
     *     @SWG\Response(
     *         response="401",
     *         description="Unauthorized",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Unauthorized"}},
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Forbidden -- needs to have role of ATM, DATM or VATUSA Division staff member",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","message"="Forbidden"}},
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(
     *             ref="#/definitions/OK"
     *         ),
     *     )
     * )
     * @param \Illuminate\Http\Request $request
     * @param                          $examid
     * @param                          $questionid
     *
     * @return
     */
    public function putExamQuestion(Request $request, $examid, $questionid)
    {
        if (!\Auth::check()) {
            return response()->api(generate_error("Unauthorized"), 401);
        }
        $exam = Exam::find($examid);
        if (!$exam) {
            return response()->api(generate_error("Not found"), 404);
        }
        if (!RoleHelper::isSeniorStaff(
                \Auth::user()->cid, $exam->facility_id, true
            )
            && !RoleHelper::isVATUSAStaff()
        ) {
            return response()->api(generate_error("Forbidden"), 403);
        }
        $question = ExamQuestions::find($questionid);
        if (!$question) {
            return response()->api(generate_error("Not found"), 404);
        }
        if ($question->exam_id != $examid) {
            return response()->api(generate_error("Malformed request"), 400);
        }
        $question->exam_id = $examid;
        $question->question = $request->input("question");
        if ($request->input("type") == "multiple") {
            $question->type = 1;
            $question->answer = $request->input("choice1");
            $question->alt1 = $request->input("choice2");
            $question->alt3 = $request->input("choice3");
            $question->alt4 = $request->input("choice4");
        } elseif ($request->input("type") == "truefalse") {
            if ($request->input("choice1") != "True"
                && $request->input(
                    "choice1"
                ) != "False"
            ) {
                return response()->api(generate_error("Invalid answer"), 400);
            }
            $question->type = 0;
            $question->answer = $request->input("choice1");
        }
        $question->save();

        return response()->api(["status" => "OK", "id" => $question->id]);
    }

    /**
     *
     * @SWG\Post(
     *     path="/exam/(id)/assign/(cid)",
     *     summary="Assign exam. [Auth]",
     *     description="Assign exam to specified controller. Requires JWT or Session Cookie. Must be instructor, senior
           staff or VATUSA staff.", tags={"user","exam"}, produces={"application/json"},
     *     @SWG\Parameter(name="id", in="path", type="integer", description="Exam ID"),
     *     @SWG\Parameter(name="cid", in="path", type="integer", description="VATSIM ID"),
     *     @SWG\Parameter(name="expire", in="formData", type="integer", description="Days until expiration, 7
     *                                   default"),
     *     @SWG\Response(
     *         response="401",
     *         description="Unauthorized",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Unauthorized"}},
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Forbidden -- needs to have role of ATM, DATM or VATUSA Division staff member",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","message"="Forbidden"}},
     *     ),
     *     @SWG\Response(
     *         response="409",
     *         description="Conflict - likely means already assigned",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","message"="Forbidden"}},
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(
     *             ref="#/definitions/OK"
     *         ),
     *     )
     * )
     * @param \Illuminate\Http\Request $request
     * @param                          $examid
     * @param                          $cid
     *
     * @return \Illuminate\Http\Response
     */
    public function postExamAssign(Request $request, $examid, $cid)
    {
        if (!\Auth::check()) {
            return response()->api(generate_error("Unauthorized"), 401);
        }
        if (!RoleHelper::isSeniorStaff() &&
            !RoleHelper::isInstructor() &&
            !RoleHelper::isVATUSAStaff()) {
            return response()->api(generate_error("Forbidden"), 403);
        }

        $exam = Exam::find($examid);
        if (!$exam) {
            return response()->api(generate_error("Not found"), 404);
        }

        if (ExamAssignment::hasAssignment($cid, $examid)) {
            return response()->api(generate_error("Conflict"), 409);
        }

        $days = $request->input("expire", 7);

        if (!isTest()) {
            $ea = new ExamAssignment();
            $ea->cid = $cid;
            $ea->instructor_id = \Auth::user()->cid;
            $ea->exam_id = $examid;
            $ea->assigned_date = Carbon::now();
            $ea->expire_date = Carbon::create()->addDays($days);
            $ea->save();

            if ($exam->cbt_required > 0) {
                $cbt = TrainingBlock::find($exam->cbt_required);
            }

            $data = [
                'exam_name'       => "(" . $exam->facility_id . ") " . $exam->name,
                'instructor_name' => \Auth::user()->fullname(),
                'end_date'        => Carbon::create()->addDays($days)->toDayDateTimeString(),
                'student_name'    => User::find($cid)->fullname(),
                'cbt_required'    => $exam->cbt_required,
                'cbt_facility'    => (isset($cbt)) ? $cbt->facility_id : null,
                'cbt_block'       => (isset($cbt)) ? $exam->cbt_reuqired : null
            ];
            $to[] = User::find($cid)->email;
            $to[] = \Auth::user()->email;
            if ($exam->facility_id != "ZAE") {
                $to[] = $exam->facility_id . "-TA@vatusa.net";
            }

            EmailHelper::sendEmailFacilityTemplate($to, "Exam Assigned", $exam->facility_id, "examassigned", $data);

            log_action($cid, "Exam (" . $exam->facility_id . ") " . $exam->name .
                " assigned by " . \Auth::user()->fullname() . ", expires " . $data['end_date']);
        }

        return response()->api(['status' => 'OK']);
    }

    /**
     *
     * @SWG\Delete(
     *     path="/exam/(id)/assign/(cid)",
     *     summary="Delete exam assignment. [Auth]",
     *     description="Delete user's exam assignment. Requires JWT or Session Cookie.",
     *     tags={"user","exam"},
     *     produces={"application/json"},
     *     @SWG\Parameter(name="id", in="path", type="integer", description="Exam ID Number"),
     *     @SWG\Parameter(name="cid", in="path", type="integer", description="CERT ID"),
     *     @SWG\Response(
     *         response="401",
     *         description="Unauthorized",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Unauthorized"}},
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Forbidden -- needs to have role of INS, ATM, DATM, or VATUSA Division staff member",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","message"="Forbidden"}},
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(
     *             ref="#/definitions/OK"
     *         ),
     *     )
     * )
     * @param \Illuminate\Http\Request $request
     * @param                          $examid
     * @param                          $cid
     *
     * @return \Illuminate\Http\Response
     * @throws \Exception
     */
    public function deleteExamAssignment(Request $request, $examid, $cid)
    {
        if (!\Auth::check()) {
            return response()->api(generate_error("Unauthorized"), 401);
        }
        if (!RoleHelper::isSeniorStaff()
            && !RoleHelper::isInstructor()
            && !RoleHelper::isVATUSAStaff()
        ) {
            return response()->api(generate_error("Forbidden"), 403);
        }

        $exam = Exam::find($examid);
        if (!$exam) {
            return response()->api(generate_error("Not found"), 404);
        }
        if (!ExamAssignment::hasAssignment($cid, $examid)) {
            return response()->api(generate_error("Conflict"), 409);
        }
        if (!isTest()) {
            ExamAssignment::where('cid', $cid)->where('exam_id', $examid)->delete();
            ExamReassignment::where('cid', $cid)->where('exam_id', $examid)->delete();
            log_action($cid, "Exam (" . $exam->facility_id . ") " . $exam->name .
                " unassigned by " . \Auth::user()->fullname());
        }

        return response()->api(['status' => 'OK']);
    }

    /**
     *
     * @SWG\Get(
     *     path="/exam/result/(id)",
     *     summary="Get exam results by ID. [Key]",
     *     description="Get Exam Results filtered specifically by CERT ID.",
     *     tags={"user","exam"},
     *     produces={"application/json"},
     *     @SWG\Parameter(name="id", in="path", type="integer", description="Exam ID"),
     *     @SWG\Response(
     *         response="401",
     *         description="Unauthorized",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","msg"="Unauthorized"}},
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Forbidden -- needs to have role of INS, ATM, DATM, TA, or VATUSA Division staff member",
     *         @SWG\Schema(ref="#/definitions/error"),
     *         examples={"application/json":{"status"="error","message"="Forbidden"}},
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="OK",
     *         @SWG\Schema(
     *             ref="#/definitions/OK"
     *         ),
     *         examples={"application/json":{{
     *              "id"=0,
     *              "exam_id"=0,
     *              "exam_name"="string",
     *              "cid"=0,
     *              "score"=0,
     *              "passed"=0,
     *              "date"="string",
     *              "questions"={}
     *         },
     *         "status"="OK",
     *         "testing"=false
     *         }
     *         },
     *     )
     * )
     * @param \Illuminate\Http\Request $request
     * @param                          $id
     *
     * @return \Illuminate\Http\Response
     * @throws \Exception
     */
    public function getResult(Request $request, $id) 
    {
        $apikey = AuthHelper::validApiKeyv2($request->input('apikey', null));
        if (!$apikey && !\Auth::check()) {
            return response()->api(generate_error("Unauthorized"), 401);
        }

        if (\Auth::check() && !(RoleHelper::isSeniorStaff() ||
                RoleHelper::isVATUSAStaff() ||
                RoleHelper::isInstructor())) {
            return response()->api(generate_error("Forbidden"), 403);
        }

        $results = ExamResults::find($id)->toArray();
        if (!$results) {
            return response()->api(generate_error("Not found"), 404);
        }

        if (\Auth::check() && RoleHelper::isSeniorStaff() || RoleHelper::isVATUSAStaff() || RoleHelper::isInstructor()) {
            $questions = ExamResultsData::where("result_id", $id)->get()->toArray();
        } else {
            $questions = null;
        }

        return response()->ok([array_merge($results, ["questions" => $questions])]);
    }
}
