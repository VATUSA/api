<?php

namespace App\Console\Commands;

use App\Facility;
use App\Helpers\RatingHelper;
use App\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Symfony\Component\Console\Helper\Helper;

class StatsMonthly extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stats:monthly';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate and store monthly facility controller statistics';

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
        $summary = [];
        foreach(Facility::where('active', 1)->orWhere('id','ZAE')->orWhere('id','ZHQ')->get() as $facility) {
            // Reset data
            $data = [
                'id' => $facility->id,
                'name' => $facility->name,
                'OBS' => 0,
                'OBSg30' => 0,
                'S1' => 0,
                'S2' => 0,
                'S3' => 0,
                'C1' => 0,
                'I1' => 0,
                'SUP' => 0,
                'total' => 0,
            ];

            foreach(User::where('facility', $facility->id)->get() as $user) {
                switch($user->rating) {
                    case RatingHelper::shortToInt("OBS"):
                        if(Carbon::createFromFormat("Y-m-d H:i:s", $user->facility_join)
                            ->addDays(30)->isFuture()) {
                            $data['OBS']++;
                        } else {
                            $data['OBSg30']++;
                        }
                        break;
                    case RatingHelper::shortToInt("S1"):
                        $data['S1']++;
                        break;
                    case RatingHelper::shortToInt("S2"):
                        $data['S2']++;
                        break;
                    case RatingHelper::shortToInt("S3"):
                        $data['S3']++;
                        break;
                    case RatingHelper::shortToInt("C1"):
                    case RatingHelper::shortToInt("C2"):
                    case RatingHelper::shortToInt("C3"):
                        $data['C1']++;
                        break;
                    case RatingHelper::shortToInt("I1"):
                    case RatingHelper::shortToInt("I2"):
                    case RatingHelper::shortToInt("I3"):
                        $data['I1']++;
                        break;
                    case RatingHelper::shortToInt("SUP"):
                    case RatingHelper::shortToInt("ADM"):
                        $data['SUP']++;
                        break;
                }
                $data['total']++;
            }
            $summary[$facility->id] = $data;
        }

        \DB::table("stats_archive")->insert([
            'date' => Carbon::today(),
            'data' => encode_json($summary)
        ]);
    }
}
