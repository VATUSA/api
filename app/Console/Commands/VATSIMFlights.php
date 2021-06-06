<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class VATSIMFlights extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vatsim:flights';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update cache of VATSIM flights';

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
     * @return void
     */
    public function handle()
    {
        $status = file_get_contents("https://status.vatsim.net/status.json");
        if (!$status) {
            Log::notice("There was an error retrieving VATSIM data from server... received header:" . json_encode($http_response_header));

            return 1;
        }
        $server = json_decode($status, true)["data"]["v3"][0] ?? null;
        if (!$server) {
            Log::notice("There was an error retrieving VATSIM data from server. The array is invalid.");

            return 1;
        }
        $data = file_get_contents($server);
        if (!$data) {
            Log::notice("There was an error retrieving VATSIM data from server... received header:" . json_encode($http_response_header));

            return 1;
        }
        $vdata = json_decode($data, true);
        $pilots = $vdata["pilots"];
        foreach ($pilots as $pilot) {
            if (!isset($pilot["latitude"], $pilot["longitude"]) || $pilot["latitude"] < 0 || $pilot["longitude"] > -20) {
                continue;    // Only log part of our hemisphere
            }
            $planes[] = [
                'callsign' => $pilot["callsign"] ?? "",
                'cid'      => $pilot["cid"] ?? 0,
                'type'     => $pilot["flight_plan"]["aircraft_short"] ?? "",
                'dep'      => $pilot["flight_plan"]["departure"] ?? "",
                'arr'      => $pilot["flight_plan"]["arrival"] ?? "",
                'route'    => $pilot["flight_plan"]["route"] ?? "",
                'lat'      => $pilot["latitude"] ?? "",
                'lon'      => $pilot["longitude"] ?? "",
                'hdg'      => $pilot["heading"] ?? 0,
                'spd'      => $pilot["groundspeed"] ?? 0,
                'alt'      => $pilot["altitude"] ?? 0
            ];
        }

        \Cache::put("vatsim.data", json_encode($planes ?? [], JSON_NUMERIC_CHECK), 60);      // Keep 1 minute
        return 0;
    }
}
