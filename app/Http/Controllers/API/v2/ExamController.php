<?php
namespace App\Http\Controllers\API\v2;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Exam;

class ExamController extends Controller
{
    public function getRequest(Request $request) {
        // $exam = Session::get();  -- get from session, match assignment with exam..

        // For now: @TODO DEBUG REMOVAL
        $exam = Exam::find(5);

        // @TODO if (!ExamHelper::examCBTComplete($exam))

        if ($exam->number > 0)
            $questions = $exam->questions()->orderBy(\DB::raw('RAND()'))->take($exam->number)->get();
        else
            $questions = $exam->questions()->orderBy(\DB::raw('RAND()'))->get();

        $json = [
            'id' => $exam->id,
            'name' => $exam->name,
        ];
        foreach ($questions as $question) {
            $questiontemp = [
                'id' => $question->id,
                'question' => preg_replace("/\r?\n/", '<br>', $question->question),
                'illustration' => $question->illustration,
                'type' => $question->type
            ];
            if ($question->type == 0) {
                $questiontemp['a'] = preg_replace("/\r?\n/", '<br>', $question->answer);
                $questiontemp['b'] = preg_replace("/\r?\n/", '<br>', $question->alt1);
                $questiontemp['c'] = preg_replace("/\r?\n/", '<br>', $question->alt2);
                $questiontemp['d'] = preg_replace("/\r?\n/", '<br>', $question->alt3);
            } else {
                $questiontemp['a'] = $question->answer;
            }

            $json['questions'][] = $questiontemp;
        }
        $json = json_encode($json, JSON_HEX_APOS | JSON_NUMERIC_CHECK);
        $sig = md5($json);
        return response()->json([
            'payload' => base64_encode($json) . "." . $sig
        ]);
    }
}
