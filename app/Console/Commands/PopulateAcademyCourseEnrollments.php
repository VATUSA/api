<?php

namespace App\Console\Commands;

use App\Classes\VATUSAMoodle;
use App\Facility;
use App\AcademyCourse;
use App\AcademyCourseEnrollment;
use App\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PopulateAcademyCourseEnrollments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'moodle:populate_enrollments';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update AcademyCourseEnrollments';

    /** @var \App\Classes\VATUSAMoodle instance */
    private $moodle;

    /**
     * Create a new command instance.
     *
     * @param \App\Classes\VATUSAMoodle $moodle
     */
    public function __construct(VATUSAMoodle $moodle)
    {
        parent::__construct();
        $this->moodle = $moodle;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     * @throws \Exception
     */
    public function handle()
    {
        // Needed Enrollments
        $needed_enrollments = DB::select("SELECT c.cid, ac.id as academy_course_id FROM controllers c ".
            "JOIN academy_course ac " .
            "LEFT JOIN academy_course_enrollment ace ON ac.id = ace.academy_course_id AND c.cid = ace.cid ".
            "WHERE flag_homecontroller = 1 AND c.rating > 0 AND ace.id IS NULL");

        foreach ($needed_enrollments as $ne) {
            echo "Creating Enrollment for CID " . $ne->cid . " Course " . $ne->academy_course_id . "\n";
            $e = new AcademyCourseEnrollment();
            $e->cid = $ne->cid;
            $e->academy_course_id = $ne->academy_course_id;
            $e->assignment_timestamp = null;
            $e->passed_timestamp = null;
            $e->status = AcademyCourseEnrollment::$STATUS_NOT_ENROLLED;
            $e->save();
        }

        $enrollments = AcademyCourseEnrollment::where('status', '<', AcademyCourseEnrollment::$STATUS_COMPLETED)->limit(1000)->get();

        foreach ($enrollments as $e) {
            $hasChange = false;

            try {
                $uid = $this->moodle->getUserId($e->cid);
            } catch (Exception $e) {
                $uid = -1;
            }

            if ($e->status < AcademyCourseEnrollment::$STATUS_ENROLLED) {
                $assignmentDate = $this->moodle->getUserEnrolmentTimestamp($uid, $e->course->moodle_enrol_id);
                $assignmentTimestamp = $assignmentDate ?
                    Carbon::createFromTimestampUTC($assignmentDate)->format('Y-m-d H:i') : null;

                if ($assignmentTimestamp) {
                    $e->assignment_timestamp = $assignmentTimestamp;
                    $e->status = AcademyCourseEnrollment::$STATUS_ENROLLED;
                    $hasChange = true;
                }
            }

            if ($e->status == AcademyCourseEnrollment::$STATUS_ENROLLED) {
                $attempts = $this->moodle->getQuizAttempts($e->course->moodle_quiz_id, null, $uid);
                foreach($attempts as $attempt) {
                    if (round($attempt['grade']) > $e->course->passing_percent) {
                        // Passed
                        $finishTimestamp =
                            Carbon::createFromTimestampUTC($attempt['timefinish'])->format('Y-m-d H:i');
                        $e->passed_timestamp = $finishTimestamp;
                        $e->status = AcademyCourseEnrollment::$STATUS_COMPLETED;
                        $hasChange = true;
                    }
                }
            }

            if ($e->status < AcademyCourseEnrollment::$STATUS_COMPLETED && $e->user->rating >= $e->course->rating) {
                $e->status = AcademyCourseEnrollment::$STATUS_EXEMPT;
                $hasChange = true;
            }

            if ($hasChange) {
                $e->save();
            }
        }
    }
}
