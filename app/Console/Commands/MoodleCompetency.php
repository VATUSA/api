<?php

namespace App\Console\Commands;

use App\AcademyCompetency;
use App\Classes\VATUSAMoodle;
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
    protected $signature = 'moodle:competency';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update AcademyCompetency';

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
        // Missing Competencies
        $missing_competencies = DB::select("SELECT c.cid, ac.id as academy_course_id, ac.rating, ac.moodle_enrol_id, ac.moodle_quiz_id, ac.passing_percent
                                                    FROM controllers c
                                                             JOIN academy_course ac ON c.rating = ac.rating OR c.rating = ac.rating - 1
                                                             LEFT JOIN academy_competency comp ON ac.id = comp.academy_course_id AND c.cid = comp.cid
                                                    WHERE flag_homecontroller = 1
                                                      AND c.rating > 0
                                                      AND c.rating < 5
                                                      AND (c.rating > 1 OR c.facility != 'ZAE')
                                                      AND c.lastactivity > NOW() - INTERVAL 180 DAY
                                                      AND (comp.id IS NULL OR comp.expiration_timestamp < NOW())");
        $total = count($missing_competencies);
        echo "{$total} competencies to process\n";
        $i = 0;
        foreach ($missing_competencies as $mc) {
            try {
                $uid = $this->moodle->getUserId($mc->cid);
            } catch (Exception) {
                echo "Can't get moodle user id for CID {$mc->cid}\n";
                continue;
            }
            $i++;
            echo "[{$i}/{$total}] Checking for missing competency - CID: {$mc->cid} - Rating: {$mc->rating} - Quiz Id: {$mc->moodle_quiz_id}\n";
            $attempts = $this->moodle->getQuizAttempts($mc->moodle_quiz_id, null, $uid);
            foreach ($attempts as $attempt) {
                if (round($attempt['grade']) > $mc->passing_percent) {
                    // Passed
                    $finishCarbon = Carbon::createFromTimestampUTC($attempt['timefinish']);
                    $finishTimestamp = $finishCarbon->format('Y-m-d H:i');
                    $expireCarbon = $finishCarbon->addDays(180);
                    $expireTimestamp = $expireCarbon->format('Y-m-d H:i');
                    $finishDaysAgo = Carbon::now()->diffInDays($finishCarbon);
                    if ($finishDaysAgo < 180) {
                        $c = new AcademyCompetency();
                        $c->cid = $mc->cid;
                        $c->academy_course_id = $mc->academy_course_id;
                        $c->completion_timestamp = $finishTimestamp;
                        $c->expiration_timestamp = $expireTimestamp;
                        $c->save();
                        echo "===Detected valid quiz pass - CID: {$mc->cid} - Rating: {$mc->rating} - Quiz Id: {$mc->moodle_quiz_id}\n";
                    }
                }
            }
        }
    }
}
