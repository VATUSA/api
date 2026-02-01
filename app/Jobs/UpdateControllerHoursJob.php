<?php

namespace App\Jobs;

use App\Helpers\Helper;
use App\Helpers\VATSIMApi2Helper;
use App\Models\ControllerEligibilityCache;
use App\User;
use Carbon\Carbon;
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
     * @param int $cid
     * @return void
     */
    public function __construct(int $cid)
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
        $user = User::find($this->cid);
        if (!$user) {
            Log::warning("UpdateControllerHoursJob: User {$this->cid} not found.");
            return;
        }

        $controllerEligibility = ControllerEligibilityCache::where('cid', $this->cid)->first();
        if (!$controllerEligibility) {
            Log::warning("UpdateControllerHoursJob: ControllerEligibilityCache record for {$this->cid} not found.");
            return;
        }

        $short = strtolower(Helper::ratingShortFromInt($user->rating));

        try {
            // Attempt to fetch rating hours up to 3 times, with a short delay (e.g., 5 seconds)
            // instead of blocking for a full minute.
            $attempt = 0;
            $ratingHours = null;
            while ($attempt < 3 && $ratingHours === null) {
                $attempt++;
                $ratingHours = VATSIMApi2Helper::fetchRatingHours($user->cid);
                if ($ratingHours === null && $attempt < 3) {
                    sleep(5); // Wait for 5 seconds before retrying
                }
            }
            
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
                Log::error("UpdateControllerHoursJob: Failed to retrieve rating hours for {$user->cid} after multiple attempts.");
            }
        } catch (\Exception $e) {
            Log::error("UpdateControllerHoursJob: Exception processing hours for {$user->cid}: " . $e->getMessage());
        }

        $controllerEligibility->save();
    }
}
