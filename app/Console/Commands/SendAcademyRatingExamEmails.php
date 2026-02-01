<?php

namespace App\Console\Commands;

use App\AcademyBasicExamEmail;
use App\AcademyExamAssignment;
use App\Classes\VATUSAMoodle;
use App\Helpers\Helper;
use App\Http\Middleware\PrivateCORS;
use App\Mail\AcademyExamSubmitted;
use App\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class SendAcademyRatingExamEmails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'moodle:sendexamemails';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Checks for final exam attempts and sends emails.';

    private $moodle;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->moodle = new VATUSAMoodle();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     * @throws \Exception
     */
    public function handle()
    {
        foreach (AcademyExamAssignment::with(['student', 'instructor'])->get() as $assignment) {
            $student = $assignment->student;
            $studentName = $student->name;
            $instructor = $assignment->instructor;
            $quizId = $assignment->quiz_id;
            $attemptEmailsSent = $assignment->attempt_emails_sent ? explode(',', $assignment->attempt_emails_sent) : [];

            if ($assignment->created_at->diffInDays(Carbon::now()) > 30) {
                log_action($assignment->student->cid,
                    "Academy exam course enrollment expired - $assignment->course_name - greater than 30 days");
                $assignment->delete();
                continue;
            }

            //Check for attempts
            $attempts = $this->moodle->getQuizAttempts($quizId, null, $assignment->moodle_uid);
            foreach ($attempts as $attempt) {
                if (in_array($attempt['attempt'], $attemptEmailsSent) || !in_array($attempt['quiz'], [
                        config('exams.S2.id'),
                        config('exams.S3.id'),
                        config('exams.C1.id')
                    ]) || $attempt['state'] !== "finished") {
                    continue;
                }

                $attemptNum = $attempt['attempt'];
                $attemptId = $attempt['id'];
                if ($attempt['quiz'] == config('exams.S2.id')) {
                    $passingGrade = config('exams.S2.passingPercent');
                    $testName = "S2 Rating (TWR) Controller";
                } elseif ($attempt['quiz'] == config('exams.S3.id')) {
                    $passingGrade = config('exams.S3.passingPercent');
                    $testName = "S3 Rating (APP/DEP) Controller";
                } else {
                    $passingGrade = config('exams.C1.passingPercent');
                    $testName = "C1 Rating (CTR) Controller";
                }
                $grade = $attempt['grade'];
                $passed = $grade >= $passingGrade;

                $result = compact('testName', 'studentName', 'attemptNum', 'grade',
                    'passed', 'passingGrade', 'attemptId');

                $mail = Mail::to($student)->cc($instructor);
                //if ($attemptNum == 3 && !$passed) {
                $mail->bcc(['training@vatusa.net']);
                //}
                $mail->queue(new AcademyExamSubmitted($result));

                if ($passed) {
                    $assignment->delete();
                } else {
                    $attemptEmailsSent[] = $attemptNum;
                    $assignment->attempt_emails_sent = implode(',', $attemptEmailsSent);
                    $assignment->save();
                }

                log_action($student->cid,
                    "Academy exam submitted - $testName - Attempt $attemptNum - $grade%");
            }
        }

        //Send emails and add action log on passing of Basic ATC Exam.
        //Use DB only.
        //Get all quiz attempts with quiz ID from config and where timefinish is within the last week.
        //Skip if ID exists in academy_basic_exam_emails table.
        //Send results to controller, same as above.
        //Add to action log, same as above.
        //Add to academy_basic_exam_emails table.
        //Tracks attempt ID of attempts already processed
        //Delete from academy_basic_exam_emails more than a week old.

        $weekInterval = Carbon::now()->subWeek();

        $attempts = DB::connection('moodle')->table('quiz_attempts')
            ->where('quiz', config('exams.BASIC.id'))
            ->where('state', 'finished')
            ->where('timefinish', '>=', $weekInterval->timestamp)
            ->get();
        $moodleAttemptIds = $attempts->pluck('id')->toArray();
        $processedAttemptIds = AcademyBasicExamEmail::whereIn('attempt_id', $moodleAttemptIds)->pluck('attempt_id')->toArray();

        $cidsFromMoodleUsers = [];
        $attemptsToProcess = $attempts->filter(function($attempt) use ($processedAttemptIds) {
            return !in_array($attempt->id, $processedAttemptIds);
        });

        foreach ($attemptsToProcess as $attempt) {
            // Note: $this->moodle->getCidFromUserId is likely an external API call, which is an N+1 with Moodle API.
            // A batch version of getCidFromUserId would be ideal here if Moodle API supports it.
            $cidsFromMoodleUsers[$attempt->id] = $this->moodle->getCidFromUserId($attempt->userid);
        }

        $studentsByCid = User::whereIn('cid', array_filter($cidsFromMoodleUsers))->get()->keyBy('cid');

        foreach ($attempts as $attempt) {
            if (in_array($attempt->id, $processedAttemptIds)) {
                continue; // Skip if already processed
            }

            $cid = $cidsFromMoodleUsers[$attempt->id] ?? null;
            $student = $studentsByCid->get($cid);

            if (!$student) {
                continue;
            }

            $studentName = $student->fullname;
            $attemptNum = $attempt->attempt;
            $attemptId = $attempt->id;
            $passingGrade = config('exams.BASIC.passingPercent');
            $testName = "Basic ATC/S1 Exam";

            // Note: $this->moodle->request("mod_quiz_get_attempt_review") is an external Moodle API call,
            // which is a potential N+1. Consider batching review requests if Moodle API supports it.
            $review = $this->moodle->request("mod_quiz_get_attempt_review",
                    ["attemptid" => $attemptId]) ?? [];
            if (empty($review)) {
                continue;
            }

            $grade = round(floatval($review['grade']));
            $passed = $grade >= $passingGrade;

            $result = compact('testName', 'studentName', 'attemptNum', 'grade',
                'passed', 'passingGrade', 'attemptId');
            $mail = Mail::to($student);
            //if ($attemptNum == 3 && !$passed) {
            $mail->bcc(['training@vatusa.net']);
            //}
            $mail->queue(new AcademyExamSubmitted($result));

            if ($passed && $student->rating <= Helper::ratingIntFromShort("S1")) {
                $student->flag_needbasic = 0;
                $student->save();
            }

            log_action($student->cid,
                "Academy exam submitted - $testName - Attempt $attemptNum - $grade%");

            $record = new AcademyBasicExamEmail();
            $record->attempt_id = $attempt->id;
            $record->student_id = $student->cid;
            $record->save();
        }
        AcademyBasicExamEmail::where('created_at', '<', $weekInterval->subDays(2))->delete();
    }
}
