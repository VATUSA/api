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
    public function updateControllerEligibility(ControllerEligibilityCache $controllerEligibility) {
        $user = User::where('cid', $controllerEligibility->cid)->first();
        $in_vatusa_facility = !in_array($user->facility, ["ZAE", "ZZN", "ZZI"]);
        $visits_vatusa_facility = Visit::where('cid', $user->cid)->count() > 0;

        if ($user->flag_homecontroller) {
            // Checks for in division only
            if ($controllerEligibility->is_initial_selection !== false || $controllerEligibility->first_selection_date === null) {
                $first_transfer = Transfer::where('cid', $user->cid)
                    ->where('to', '!=', 'ZAE')
                    ->where('to', '!=', 'ZZN')
                    ->where('to', '!=', 'ZZI')
                    ->orderBy('created_at', 'asc')
                    ->first();
                if ($first_transfer) {
                    $controllerEligibility->first_selection_date = $first_transfer->created_at;
                    $controllerEligibility->is_initial_selection = false;
                } else {
                    $controllerEligibility->is_initial_selection = true;
                }
            }
            $last_promotion = Promotion::where('cid', $user->cid)
                ->where('from', '<', 5)
                ->where('to', '<', 7)
                ->orderBy('created_at', 'desc')
                ->first();
            if ($last_promotion) {
                $carbonDate = Carbon::createFromFormat('Y-m-d H:i:s', $last_promotion->created_at);
                $controllerEligibility->last_promotion_date = $carbonDate->toDateString();
            }
            $last_transfer = Transfer::where('cid', $user->cid)
                ->where('to', '!=', 'ZAE')
                ->where('to', '!=', 'ZZN')
                ->where('to', '!=', 'ZZI')
                ->orderBy('created_at', 'desc')
                ->first();
            if ($last_transfer) {
                $carbonDate = Carbon::createFromFormat('Y-m-d H:i:s', $last_transfer->created_at);
                $controllerEligibility->last_transfer_date = $carbonDate->toDateString();
            }
            $last_visit = Visit::where('cid', $user->cid)->orderBy('created_at', 'desc')->first();
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
                $competencies = AcademyCompetency::where('cid', $user->cid)->orderBy('completion_timestamp', 'desc')->get();
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
            if ($controllerEligibility->has_consolidation_hours !== true && (
                ($user->flag_homecontroller && $in_vatusa_facility) ||
                (!$user->flag_homecontroller && $user->rating >= 4)
                )
                && !$controllerEligibility->is_initial_selection) {
                $attempts = 0;
                while ($attempts < 3) {
                    $attempts++;
                    $ratingHours = VATSIMApi2Helper::fetchRatingHours($user->cid);
                    $short = strtolower(Helper::ratingShortFromInt($user->rating));
                    if ($ratingHours) {
                        if ($ratingHours[$short] >= 50) {
                            $controllerEligibility->has_consolidation_hours = true;
                        } else if ($user->rating > 5 && ($ratingHours['c1'] + $ratingHours['c3'] + $ratingHours['i1'] + $ratingHours['i3']) >= 50) {
                            $controllerEligibility->has_consolidation_hours = true;
                        } else if ($user->rating > 5) {
                            $controllerEligibility->has_consolidation_hours = false;
                            $controllerEligibility->consolidation_hours =
                                ($ratingHours['c1'] + $ratingHours['c3'] + $ratingHours['i1'] + $ratingHours['i3']);
                        } else {
                            $controllerEligibility->has_consolidation_hours = false;
                            $controllerEligibility->consolidation_hours = $ratingHours[$short];
                        }
                        break;
                    } else {
                        echo "===Rating hours object returned as empty for {$user->cid}\n";
                        sleep(60);
                    }
                }
            }
        }
        $controllerEligibility->save();
    }

    private function checkControllerHours(ControllerEligibilityCache $controllerEligibility) {
        $user = User::where('cid', $controllerEligibility->cid)->first();
        $in_vatusa_facility = !in_array($user->facility, ["ZAE", "ZZN", "ZZI"]);

        if ($user->rating > 1) {
            if ($controllerEligibility->has_consolidation_hours !== true && (
                    ($user->flag_homecontroller && $in_vatusa_facility) ||
                    (!$user->flag_homecontroller && $user->rating >= 4)
                )
                && !$controllerEligibility->is_initial_selection) {
                $ratingHours = VATSIMApi2Helper::fetchRatingHours($user->cid);
                $short = strtolower(Helper::ratingShortFromInt($user->rating));
                if ($ratingHours) {
                    if ($ratingHours[$short] >= 50) {
                        $controllerEligibility->has_consolidation_hours = true;
                    } else if ($user->rating > 5 && ($ratingHours['c1'] + $ratingHours['c3'] + $ratingHours['i1'] + $ratingHours['i3']) >= 50) {
                        $controllerEligibility->has_consolidation_hours = true;
                    } else if ($user->rating > 5) {
                        $controllerEligibility->has_consolidation_hours = false;
                        $controllerEligibility->consolidation_hours =
                            ($ratingHours['c1'] + $ratingHours['c3'] + $ratingHours['i1'] + $ratingHours['i3']);
                    } else {
                        $controllerEligibility->has_consolidation_hours = false;
                        $controllerEligibility->consolidation_hours = $ratingHours[$short];
                    }
                } else {
                    echo "===Rating hours object returned as empty for {$user->cid}\n";
                }
            }
        }
        $controllerEligibility->save();
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
            $rec = ControllerEligibilityCache::where('cid', $cid)->first();
            if (!$rec) {
                $rec = $this->createRecord($cid);
            }
            $this->updateControllerEligibility($rec);
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
            echo "[{$i}/{$total}] Creating record for {$cid->cid}\n";
            $this->createRecord($cid->cid);
        }

        $records = ControllerEligibilityCache::get();
        $total = count($records);
        $i = 0;
        foreach ($records as $record) {
            $i++;
            echo "[{$i}/{$total}] Updating eligibility for {$record->cid}\n";
            $this->updateControllerEligibility($record);
        }
    }
}
