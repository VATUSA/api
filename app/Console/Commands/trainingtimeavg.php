<?php

namespace App\Console\Commands;

use App\Helpers\RatingHelper;
use Carbon\Carbon;
use Illuminate\Console\Command;

class trainingtimeavg extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'training:average';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Compute training time averages';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        @unlink("data.C1.csv");
        @unlink("data.S1.csv");
        @unlink("data.S2.csv");
        @unlink("data.S3.csv");

        $result = \DB::table('promotions')
            ->where('promotions.created_at', '>=', '2016-01-01 00:00:00')
            ->where('promotions.created_at', '<=', '2016-12-31 23:59:59')
            ->where('promotions.to', '<=', 5)
            ->where('promotions.from', '<=', 5)
            ->get();
        foreach ($result as $row) {
            if ($row->to === $row->from) continue;
            // Step 1. Find facility member was in when rating granted
            $facilityResult = \DB::table('transfers')
                ->where('cid', $row->cid)
                ->where('created_at', '<=', $row->created_at)
                ->where('to', 'NOT LIKE', 'ZAE')
                ->orderBy('created_at', 'DESC')
                ->limit(1)->first();
            // If facility is ZAE, skip
            if (!$facilityResult) { echo "Fault, no transfer history for $row->cid\n"; continue; }
            if ($facilityResult->to == "ZAE") { echo "Got promotion for $row->cid while in ZAE\n"; continue; }

            // Step 2. Find date of last rating
            $lastRating = 0;
            if ($row->from > 1) {
                $ratingResult = \DB::table('promotions')
                    ->where('cid', $row->cid)
                    ->where('to', $row->from)->first();
                if (!$ratingResult) { $lastRating = 0; }
                else { $lastRating = $ratingResult->created_at; }
            }

            // Step 3. Find time user was in facility
            $totalDays = 0; $dateJoined = 0;
            $transfers = \DB::table('transfers')
                ->where('cid', $row->cid)
                ->where(function ($query) use ($facilityResult) {
                    $query->where('to', $facilityResult->to)
                        ->orWhere('from', $facilityResult->to);
                });
            if ($lastRating !== 0) { $transfers = $transfers->where('created_at', '>=', $lastRating); }
            $transfers = $transfers->where('created_at', '<=', $row->created_at)->orderBy('created_at', 'ASC')->get();
            foreach ($transfers as $transfer) {
                if ($transfer->to == $facilityResult->to) {
                    $dateJoined = $transfer->updated_at;
                } else {
                    if ($dateJoined === 0) { echo "Got fault with $row->cid, dateJoined is non-existent for $row->to $row->from $row->created_at promotion\n"; continue; }
                    $totalDays += abs(Carbon::createFromFormat('Y-m-d H:i:s', $dateJoined)->diffInDays(Carbon::createFromFormat('Y-m-d H:i:s', $transfer->updated_at)));
                    $dateJoined = 0;
                }
            }
            if ($dateJoined !== 0) { $totalDays += abs(Carbon::createFromFormat('Y-m-d H:i:s', $row->created_at)->diffInDays(Carbon::createFromFormat('Y-m-d H:i:s', $dateJoined))); }
            if ($totalDays === 0 && $lastRating !== 0) { // For those with no transfers
                $totalDays += abs(Carbon::createFromFormat('Y-m-d H:i:s', $row->created_at)->diffInDays(Carbon::createFromFormat('Y-m-d H:i:s', $lastRating)));
            }
            $this->log($facilityResult->to, $row->cid, $row->to, $row->from, $row->created_at, $totalDays);
        }
    }

    public function log($facility, $cid, $rating, $ratingFrom, $date, $days) {
        $fh = fopen("data." . RatingHelper::intToShort($rating) . ".csv", "a");
        fwrite($fh, "$facility,$cid,$rating,$ratingFrom,$date,$days,\n");
        fclose($fh);
    }
}
