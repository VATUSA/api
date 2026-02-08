<?php

namespace App\Console\Commands;

use App\AcademyCompetency;
use App\AcademyCourse;
use App\Classes\VATUSAMoodle;
use App\Http\Controllers\Controller;
use App\Models\ControllerEligibilityCache;
use App\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MoodleCompetency extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'moodle:competency {user? : CID of a single user to check competency for}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update AcademyCompetency';

    /** @var \App\Classes\VATUSAMoodle instance */
    private $moodle;

    private $courses;

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

    public function storeControllerCompetency(int $cid, int $rating, int $course_id, Carbon $competency_date) {
        $c = AcademyCompetency::where('cid', $cid)->where('rating', $rating)->first();
        if (!$c) {
            $c = new AcademyCompetency();
            $c->cid = $cid;
            $c->rating = $rating;
        }
        $finishTimestamp = $competency_date->format('Y-m-d H:i');
        $expireCarbon = $competency_date->addDays(180);
        $expireTimestamp = $expireCarbon->format('Y-m-d H:i');
        $c->academy_course_id = $course_id;
        $c->completion_timestamp = $finishTimestamp;
        $c->expiration_timestamp = $expireTimestamp;
        $c->save();

        $ec = ControllerEligibilityCache::where('cid', $cid)->first();
        if ($ec) {
            $ecCompetencyCarbon = Carbon::parse($ec->competency_date);
            if ($rating >= $ec->competency_rating && $ecCompetencyCarbon->isBefore($competency_date)) {
                $ec->competency_rating = $rating;
                $ec->competency_date = $competency_date->format('Y-m-d H:i');
                $ec->save();
            }
        }
    }

    public function checkControllerCompetency($cid, $rating, $controller_existing_competencies): void
    {
        if ($rating < 2) {
            return;
        }
        if ($rating > 5) {
            $rating = 5;
        }
        if (in_array($rating, $controller_existing_competencies)) {
            return;
        }
        try {
            $uid = $this->moodle->getUserId($cid);
        } catch (Exception) {
            echo "Can't get moodle user id for CID {$cid}\n";
            return;
        }
        $passedCourseId = null;
        $passedCarbon = null;
        foreach ($this->courses[$rating] as $course) {
            echo "Querying Moodle - CID: {$cid} - Rating: {$rating} - Quiz Id: {$course->moodle_quiz_id}\n";
            try {
                $attempts = $this->moodle->getQuizAttempts($course->moodle_quiz_id, null, $uid);
            } catch (Exception $e) {
                echo "Exception in moodle->getQuizAttempts(): " . $e->getMessage() . "\n\n";
                $attempts = [];
            }
            foreach ($attempts as $attempt) {
                if (round($attempt['grade']) >= $course->passing_percent) {
                    // Passed
                    $finishCarbon = Carbon::createFromTimestampUTC($attempt['timefinish']);
                    if ($passedCarbon == null || $passedCarbon->isBefore($finishCarbon)) {
                        $passedCarbon = $finishCarbon;
                        $passedCourseId = $course->id;
                    }
                    echo "===Detected valid quiz pass - CID: {$cid} - Rating: {$rating} - Quiz Id: {$course->moodle_quiz_id}\n";
                }
            }
        }
        if ($passedCourseId != null) {
            $this->storeControllerCompetency($cid, $rating, $passedCourseId, $passedCarbon);
        }

    }

    /**
     * Execute the console command.
     *
     * @return mixed
     * @throws \Exception
     */
    public function handle()
    {

        $all_courses = AcademyCourse::get();
        $this->courses = [];
        foreach ($all_courses as $course) {
            $this->courses[$course->rating][] = $course;
        }

        $all_existing_competencies = DB::select("SELECT c.cid, ac.rating
                                                        FROM academy_competency c
                                                                 JOIN academy_course ac ON c.academy_course_id = ac.id
                                                        WHERE c.expiration_timestamp > NOW()");
        $existing_competencies = [];
        foreach ($all_existing_competencies as $competency) {
            $existing_competencies[$competency->cid][] = $competency->rating;
        }
        unset($all_existing_competencies); // Dropping DB result to reclaim memory

        if ($this->argument('user')) {
            $user = User::find($this->argument('user'));
            if (!$user) {
                $this->error("Invalid CID");

                return 0;
            }


            $controller_existing_competencies = (array_key_exists($user->cid, $existing_competencies)) ? $existing_competencies[$user->cid] : [];
            if ($user->rating > 1) {
                $this->checkControllerCompetency($user->cid, $user->rating, $controller_existing_competencies);
            }
            if ($user->rating < 5 && $user->facility != 'ZZN') {
                $this->checkControllerCompetency($user->cid, $user->rating + 1, $controller_existing_competencies);
            }

            return 0;
        }

        $controllers_to_check = DB::select("SELECT c.cid, c.rating, c.facility
                                                    FROM controllers c
                                                    WHERE (
                                                        (
                                                            flag_homecontroller = 1
                                                        ) OR
                                                           (c.facility = 'ZZN' AND c.rating >= 4)
                                                        )
                                                      AND (
                                                          c.
                                                      )
                                                      AND c.rating > 0
                                                      AND c.lastactivity > NOW() - INTERVAL 1 DAY");
        $total = count($controllers_to_check);
        $i=0;
        foreach ($controllers_to_check as $controller) {
            $i++;
            echo "[{$i}/{$total}] Processing Controller {$controller->cid} - {$controller->rating}\n";
            $controller_existing_competencies = (array_key_exists($controller->cid, $existing_competencies)) ? $existing_competencies[$controller->cid] : [];
            $this->checkControllerCompetency($controller->cid, $controller->rating, $controller_existing_competencies);
            if ($controller->rating < 5 && $controller->facility != 'ZZN' && $controller->facility != 'ZAE') {
                $this->checkControllerCompetency($controller->cid, $controller->rating + 1, $controller_existing_competencies);
            }
        }
    }
}
