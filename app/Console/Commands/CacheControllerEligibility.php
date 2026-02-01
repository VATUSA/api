<?php

namespace App\Console\Commands;

use App\AcademyCompetency;
use App\AcademyCourse;
use App\Classes\VATUSAMoodle;
use App\Helpers\Helper;
use App\Helpers\VATSIMApi2Helper;
use App\Models\ControllerEligibilityCache;
use App\Promotion;
use App\Transfer;
use App\User;
use App\Visit;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CacheControllerEligibility extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'controller:eligibility {cid? : CID of a single user to calculate eligibility for}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update ControllerEligibilityCache';

    /**
     * Create a new command instance.
     *
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function createRecord(int $cid): ControllerEligibilityCache {
        $record = new ControllerEligibilityCache();
        $record->cid = $cid;
        $record->save();
        return $record;
    }
    public function updateControllerEligibility(ControllerEligibilityCache $controllerEligibility, User $user, $userTransfers, $userPromotions, $userVisits, $userAcademyCompetencies) {
        $in_vatusa_facility = !in_array($user->facility, ["ZAE", "ZZN", "ZZI"]);
        $visits_vatusa_facility = $userVisits->count() > 0; // Use pre-fetched visits

        if ($user->flag_homecontroller) {
            // Checks for in division only
            if ($controllerEligibility->is_initial_selection !== false || $controllerEligibility->first_selection_date === null) {
                $first_transfer = $userTransfers->filter(function($transfer) {
                    return !in_array($transfer->to, ['ZAE', 'ZZN', 'ZZI']);
                })->sortBy('created_at')->first();
                if ($first_transfer) {
                    $controllerEligibility->first_selection_date = $first_transfer->created_at;
                    $controllerEligibility->is_initial_selection = false;
                } else {
                    $controllerEligibility->is_initial_selection = true;
                }
            }
            $last_promotion = $userPromotions->filter(function($promotion) {
                return $promotion->from < 5 && $promotion->to < 7;
            })->sortByDesc('created_at')->first();
            if ($last_promotion) {
                $carbonDate = Carbon::createFromFormat('Y-m-d H:i:s', $last_promotion->created_at);
                $controllerEligibility->last_promotion_date = $carbonDate->toDateString();
            }
            $last_transfer = $userTransfers->filter(function($transfer) {
                return !in_array($transfer->to, ['ZAE', 'ZZN', 'ZZI']) && $transfer->status === 1;
            })->sortByDesc('created_at')->first();
            if ($last_transfer) {
                $carbonDate = Carbon::createFromFormat('Y-m-d H:i:s', $last_transfer->created_at);
                $controllerEligibility->last_transfer_date = $carbonDate->toDateString();
            }
            $last_visit = $userVisits->sortByDesc('created_at')->first(); // Use pre-fetched visits
            if ($last_visit) {
                $carbonDate = Carbon::createFromFormat('Y-m-d H:i:s',
                    $last_visit->created_at);
                if ($controllerEligibility->last_visit_date === null) {
                    $controllerEligibility->last_visit_date = $carbonDate->toDateString();
                } else {
                    $carbonCateCurrent = Carbon::createFromFormat('Y-m-d',
                        $controllerEligibility->last_visit_date);
                    if ($carbonDate->isAfter($carbonCateCurrent)) {
                        $controllerEligibility->last_visit_date = $carbonDate->toDateString();
                    }
                }
            }

        }
        if ($user->rating > 1) {
            $target_competency_rating = ($user->rating > 5) ? 5 : $user->rating;
            if ($controllerEligibility->competency_rating < $target_competency_rating) {
                $controllerEligibility->has_consolidation_hours = false;
            }
            if ($in_vatusa_facility || $visits_vatusa_facility) {
                $controllerEligibility->competency_rating = $target_competency_rating;
                $carbonDate = Carbon::now();
                $controllerEligibility->competency_date = $carbonDate->toDateString();
            }
            if ($controllerEligibility->competency_date === null
                || Carbon::createFromFormat('Y-m-d',$controllerEligibility->competency_date)
                    ->diffInDays(Carbon::now()) > 180) {
                $competencies = $userAcademyCompetencies->sortByDesc('completion_timestamp'); // Use pre-fetched
                foreach ($competencies as $competency) {
                    if ($competency->course->rating >= $controllerEligibility->competency_rating) {
                        $carbonDate = Carbon::createFromFormat('Y-m-d H:i:s', $competency->completion_timestamp);
                        if ($controllerEligibility->competency_date === null
                            || $carbonDate->isAfter(
                            Carbon::createFromFormat('Y-m-d', $controllerEligibility->competency_date))) {
                            $controllerEligibility->competency_rating = $competency->rating;
                            $controllerEligibility->competency_date = $carbonDate->toDateString();
                            $controllerEligibility->has_consolidation_hours = false;
                        }
                    }
                }
            }
        }
        $controllerEligibility->save();
    }

    private function checkControllerHours(ControllerEligibilityCache $controllerEligibility) {
        // Dispatch a job to handle the VATSIM API call and hours calculation asynchronously.
        // This prevents the main command from blocking due to slow external API calls and sleep(60).
        \App\Jobs\UpdateControllerHoursJob::dispatch($controllerEligibility->cid);
    }


    /**
     * Execute the console command.
     *
     * @return mixed
     * @throws \Exception
     */
    public function handle()
    {
        if ($this->argument('cid')) {
            $cid = $this->argument('cid');
            $user = User::find($cid);
            if (!$user) {
                $this->error("Invalid CID");
                return;
            }

            $rec = ControllerEligibilityCache::firstOrCreate(['cid' => $cid]);

            // Fetch related data for this single user
            $userTransfers = Transfer::where('cid', $cid)->get();
            $userPromotions = Promotion::where('cid', $cid)->get();
            $userVisits = Visit::where('cid', $cid)->get();
            $userAcademyCompetencies = AcademyCompetency::where('cid', $cid)->with('course')->get();

            $this->updateControllerEligibility($rec, $user, $userTransfers, $userPromotions, $userVisits, $userAcademyCompetencies);
            $this->checkControllerHours($rec);
            return;
        }

        $cids_to_check = DB::select("select c.cid
                                            from controllers c
                                                     left join controller_eligibility_cache ec on c.cid = ec.cid
                                            where c.rating > 0
                                              AND (c.flag_homecontroller OR c.rating > 3)
                                              AND ec.cid is null
                                              AND c.facility != 'ZZI'");
        $total = count($cids_to_check);
        $i = 0;
        foreach ($cids_to_check as $cid) {
            $i++;
            \Log::info("[{$i}/{$total}] Creating record for {$cid->cid}");
            $this->createRecord($cid->cid);
        }

        // General Eligibility checks
        $records = ControllerEligibilityCache::get();
        $cidsForProcessing = $records->pluck('cid')->toArray();

        // Batch fetch all related data for the CIDs
        $users = User::whereIn('cid', $cidsForProcessing)->get()->keyBy('cid'); // We need user itself
        $transfersByCid = Transfer::whereIn('cid', $cidsForProcessing)->get()->groupBy('cid');
        $promotionsByCid = Promotion::whereIn('cid', $cidsForProcessing)->get()->groupBy('cid');
        $visitsByCid = Visit::whereIn('cid', $cidsForProcessing)->get()->groupBy('cid');
        $academyCompetenciesByCid = AcademyCompetency::whereIn('cid', $cidsForProcessing)->with('course')->get()->groupBy('cid');


        $total = count($records);
        $i = 0;
        foreach ($records as $record) {
            $i++;
            \Log::info("[{$i}/{$total}] Updating eligibility for {$record->cid}");
            $user = $users->get($record->cid); // Get the user from pre-fetched collection

            if ($user) {
                $userTransfers = $transfersByCid->get($record->cid, collect());
                $userPromotions = $promotionsByCid->get($record->cid, collect());
                $userVisits = $visitsByCid->get($record->cid, collect());
                $userAcademyCompetencies = $academyCompetenciesByCid->get($record->cid, collect());
                $this->updateControllerEligibility($record, $user, $userTransfers, $userPromotions, $userVisits, $userAcademyCompetencies);
            } else {
                \Log::warning("CacheControllerEligibility: User {$record->cid} not found for eligibility update, skipping.");
            }
        }

        // Hours checks
        $records = ControllerEligibilityCache::where('has_consolidation_hours', false)
            ->where('is_initial_selection', false)
            ->where('competency_rating', '>', 1)
            ->get();
        $total = count($records); // Recalculate total as $records is re-fetched
        $i = 0;
        foreach ($records as $record) {
            $i++;
            \Log::info("[{$i}/{$total}] Checking hours for {$record->cid}");
            $this->checkControllerHours($record);
        }
    }
}
