<?php

namespace App\Console\Commands;

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
        // SELECT * FROM promotions LEFT JOIN transfers ON promotions.cid=transfers.cid WHERE promotions.created_at >= '2016-01-01 00:00:00' AND promotions.created_at <= '2016-12-31 23:59:59' AND promotions.`to` <= 5 AND promotions.`from` >= 1 AND transfers.created_at <= promotions.created_at ORDER BY transfers.created_at DESC
        $result = \DB::table('promotions')
            ->leftJoin('transfers','promotions.cid','=','transfers.cid')
            ->where('promotions.created_at', '>=', '2016-01-01 00:00:00')
            ->where('promotions.created_at', '<=', '2016-12-31 23:59:59')
            ->where('promotions.to', '<=', 5)
            ->where('promotions.from', '>=', 1)
            ->where('transfers.created_at', '<=', 'promotions.created_at')
            ->orderBy('transfers.created_at', 'DESC')
            ->select('promotions.cid','promotions.to as rating','transfers.to as facility')->get();
        // { cid: 8127125, rating: 3, facility: "ZDV" }
    }
}
