<?php

namespace App\Console\Commands;

use App\AcademyCompetency;
use App\AcademyCourse;
use App\Classes\VATUSAMoodle;
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
        foreach ($this->courses[$rating] as $course) {
            echo "Querying Moodle - CID: {$cid} - Rating: {$rating} - Quiz Id: {$course->moodle_quiz_id}\n";
            $attempts = $this->moodle->getQuizAttempts($course->moodle_quiz_id, null, $uid);
            foreach ($attempts as $attempt) {
                if (round($attempt['grade']) > $course->passing_percent) {
                    // Passed
                    $finishCarbon = Carbon::createFromTimestampUTC($attempt['timefinish']);
                    $finishTimestamp = $finishCarbon->format('Y-m-d H:i');
                    $expireCarbon = $finishCarbon->addDays(180);
                    $expireTimestamp = $expireCarbon->format('Y-m-d H:i');
                    $finishDaysAgo = Carbon::now()->diffInDays($finishCarbon);
                    if ($finishDaysAgo < 180) {
                        $c = new AcademyCompetency();
                        $c->cid = $cid;
                        $c->academy_course_id = $course->id;
                        $c->completion_timestamp = $finishTimestamp;
                        $c->expiration_timestamp = $expireTimestamp;
                        $c->save();
                        echo "===Detected valid quiz pass - CID: {$cid} - Rating: {$rating} - Quiz Id: {$course->moodle_quiz_id}\n";
                        return;
                    }
                }
            }
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
                                                        WHERE c.expiration_timestamp < NOW()");
        $existing_competencies = [];
        foreach ($all_existing_competencies as $competency) {
            $existing_competencies[$competency->cid][] = $competency->rating;
        }

        if ($this->argument('user')) {
            $user = User::find($this->argument('user'));
            if (!$user) {
                $this->error("Invalid CID");

                return 0;
            }


            $controller_existing_competencies = (array_key_exists($user->cid, $existing_competencies)) ? $existing_competencies[$user->cid] : [];
            $this->checkControllerCompetency($user->cid, $user->rating, $controller_existing_competencies);
            if ($user->rating < 5 && $user->facility != 'ZZN' && $user->facility != 'ZAE') {
                $this->checkControllerCompetency($user->cid, $user->rating + 1, $controller_existing_competencies);
            }

            return 0;
        }

        $controllers_to_check = DB::select("SELECT c.cid, c.rating, c.facility
                                                    FROM controllers c
                                                    WHERE (
                                                        (
                                                            flag_homecontroller = 1 AND (c.rating > 1 OR c.facility != 'ZAE')
                                                        ) OR
                                                           (c.facility = 'ZZN' AND c.rating >= 4)
                                                        )
                                                    
                                                      AND c.rating > 0
                                                      AND c.lastactivity > NOW() - INTERVAL 30 DAY");
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
