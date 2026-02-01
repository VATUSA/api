<?php

namespace App\Jobs;

use App\Helpers\Helper;
use App\Helpers\VATSIMApi2Helper;
use App\Models\ControllerEligibilityCache;
use App\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UpdateControllerHoursJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $cid;

    /**
     * Create a new job instance.
     *
     * @param $cid
     */
    public function __construct($cid)
    {
        $this->cid = $cid;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $controllerEligibility = ControllerEligibilityCache::where('cid', $this->cid)->first();
        if (!$controllerEligibility) {
            Log::warning("UpdateControllerHoursJob: ControllerEligibilityCache not found for {$this->cid}, skipping.");
            return;
        }

        $user = User::where('cid', $this->cid)->first();
        if (!$user) {
            Log::warning("UpdateControllerHoursJob: User {$this->cid} not found, skipping.");
            return;
        }

        $in_vatusa_facility = !in_array($user->facility, ["ZAE", "ZZN", "ZZI"]);

        if ($user->rating > 1) {
            if ($controllerEligibility->has_consolidation_hours !== true &&
                (($user->flag_homecontroller && $in_vatusa_facility) || (!$user->flag_homecontroller && $user->rating >= 4))
                && !$controllerEligibility->is_initial_selection) {
                $attempt = 0;
                while ($attempt < 3) {
                    $attempt++;
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
                        Log::warning("UpdateControllerHoursJob: Rating hours object returned as empty for {$user->cid}");
                        sleep(60);
                    }
                }
            }
        }
        $controllerEligibility->save();
    }
}
