<?php

namespace App\Http\Controllers\API\v1;

use App\ExamResults;
use App\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

/**
 * Class ExamController
 * @package App\Http\Controllers\API\v1
 */
class ExamController extends Controller
{
    /**
     * @param $apikey
     * @param $result_id
     * @return string
     */
    public function getExamResult($apikey, $result_id) {
        $result = ExamResults::find($result_id);
        if (!$result) {
            return generate_error("Exam result not found", false);
        }

        $return = [];
        $return['status'] = "success";
        $return['id'] = $result_id;
        $return['cid'] = $result->cid;
        $return['exam_id'] = $result->exam_id;
        $return['name'] = $result->exam_name;
        $return['score'] = $result->score;
        $return['passed'] = ($result->passed ? true : false);
        $return['date'] = $result->date;
        foreach($result->data as $data) {
            $d = [
                'question' => $data->question,
                'correct' => $data->correct,
                'selected' => $data->selected,
                'is_correct' => ($data->is_correct ? true : false)
            ];
            $return['questions'][] = $d;
        }

        return encode_json($return);
    }

    /**
     * @param $apikey
     * @param $cid
     * @return string
     */
    public function getUserResults($apikey, $cid) {
        if (!$cid) {
            return generate_error("CID not specified", false);
        }
        $user = User::find($cid);
        if (!$user) {
            return generate_error("CID not valid", false);
        }

        $results = ExamResults::where('cid', $cid)->orderBy('date')->get();
        $data = [];
        $data['status'] = 'success';
        $data['cid'] = $cid;
        foreach ($results as $result) {
            $exam = [];
            $exam['id'] = $result->id;
            $exam['exam_id'] = $result->exam_id;
            $exam['name'] = $result->exam_name;
            $exam['score'] = $result->score;
            $exam['passed'] = ($result->passed ? true : false);
            $exam['date'] = $result->date;
            $data['exams'][] = $exam;
        }

        return encode_json($data);
    }
}
