<?php

namespace App\Console\Commands;

use App\Classes\VATUSAMoodle;
use App\Facility;
use App\AcademyCourse;
use App\AcademyCourseEnrollment;
use App\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

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
        $academy_courses = AcademyCourse::orderBy("list_order", "ASC")->get();
        foreach (User::where('flag_homecontroller', 1)->where('rating', '>', 0)->get() as $user) {
            echo "Processing CID " . $user->cid . "\n";
            try {
                $uid = $this->moodle->getUserId($user->cid);
            } catch (Exception $e) {
                $uid = -1;
            }
            $enrollments = AcademyCourseEnrollment::where('cid', $user->cid)->get();
            $enrollment_map = [];
            foreach ($enrollments as $enrollment) {
                $enrollment_map[$enrollment->academy_course_id] = $enrollment;
            }
            foreach ($academy_courses as $academy_course) {
                if (!array_key_exists($academy_course->id, $enrollment_map)) {

                    $e = new AcademyCourseEnrollment();
                    $e->cid = $user->cid;
                    $e->academy_course_id = $academy_course->id;
                    $e->assignment_timestamp = null;
                    $e->passed_timestamp = null;
                    $e->status = AcademyCourseEnrollment::$STATUS_NOT_ENROLLED;
                    $e->save();
                    $enrollment_map[$academy_course->id] = $e;
                }
            }

            foreach ($enrollments as $e) {
                $hasChange = false;
                if ($e->status < AcademyCourseEnrollment::$STATUS_ENROLLED) {
                    $assignmentDate = $this->moodle->getUserEnrolmentTimestamp($uid, $academy_course->moodle_enrol_id);
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

                if ($hasChange) {
                    $e->save();
                }
            }
        }
    }
}
