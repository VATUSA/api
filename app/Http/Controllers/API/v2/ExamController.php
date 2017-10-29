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

class ExamController extends Controller
{
    public function postSubmit(Request $request) {
        // Make sure all is there
        if (!$request->has('payload') || !$request->has('answers')) {
            abort(400, "Missing data");
        }

        // Extract payload and verify signature, should prevent tampering
        $payload = $request->input('payload');
        $payloads = explode(".", $payload);
        if (sha1(env('EXAM_SECRET') . '$' . \Auth::user()->cid . '$' . base64_decode($payloads[0])) != $payloads[1]) {
            abort(400, "Signature doesn't match payload");
        }

        $answers = json_decode(base64_decode($request->input('answers')), true);
        $questions = json_decode(base64_decode($payloads[0]), true);

        \Log::info(json_encode($answers));

        // Verify assignment
        $assign = ExamAssignment::where('cid', \Auth::user()->cid)->where('exam_id', $questions['id'])->first();
        if (!$assign) abort(400, "Exam not assigned.");

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
            if ($answers[$id] == "one") {
                $erd->is_correct = 1;
                $erd->selected = $question['one'];
                $correct++;
            }
            else {
                $erd->selected = $question[$answers[$id]];
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

        if ($exam->passed) {
            $assign->delete();
            $fac = $exam->facility_id;
            if ($fac == "ZAE") { $fac = \Auth::user()->facility; }
            //EmailHelper::sendEmailFacilityTemplate($to, "Exam Passed", $fac, "exampassed", $data);
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
            //EmailHelper::sendEmailFacilityTemplate($to, "Exam Not Passed", $fac, "examfailed", $data);

            return response()->json(['results' => "Not Passed."]);
        }
    }

    public function getRequest(Request $request) {
        // $exam = Session::get();  -- get from session, match assignment with exam..
        $exam = 5;

        $assign = ExamAssignment::where('cid', \Auth::user()->cid)->where('exam_id', $exam)->first();
        if (!$assign) abort(404, "Exam not assigned");

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
